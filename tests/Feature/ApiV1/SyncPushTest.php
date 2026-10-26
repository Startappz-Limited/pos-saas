<?php

use App\Actions\User\ManageTillPin;
use App\Enums\ApiErrorCode;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Register;
use App\Models\Sale;
use App\Models\TillPin;
use App\Models\User;
use App\Services\InvoiceNumberService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Till;
use Tests\Support\TwoBusinesses;

/**
 * Sync push, Phase 7 (step 6): a till's outbox applied in order, once per
 * client uuid, with a result per item; sales recorded by the flat API's rules
 * with the till's own time, invoice number and cash session, only inside a
 * lease; customers with a known phone flagged; PIN sign-ins audited, with the
 * lockout that needs a manager kept on the server.
 */
beforeEach(function () {
    migrateAuditDatabase();
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));
    $this->fixture = TwoBusinesses::create();
    $this->tenant = app(TenantContext::class);
    $this->a = $this->fixture->a;
    $this->product = $this->a->product;
    $this->register = $this->tenant->run($this->a->business, fn () => Register::defaultFor($this->product->shop_id));
    $this->tenant->forget();
    // Pairing issues the lease: 09:00 to three days on.
    $this->token = Till::token($this->a->owner, $this->register);
    $this->travel(2)->hours();
});

/**
 * POST /api/v1/sync/push as the till.
 *
 * @param  list<array<string, mixed>>  $items
 */
function pushAsTill(string $token, array $items)
{
    Till::freshRequest();

    return test()->withToken($token)->postJson('/api/v1/sync/push', ['items' => $items]);
}

function pushItem(string $type, array $data, string $recordedAt = '2026-10-26 10:00:00', ?string $clientUuid = null): array
{
    return ['client_uuid' => $clientUuid ?? (string) Str::uuid(), 'type' => $type, 'recorded_at' => $recordedAt, 'data' => $data];
}

function pushedSale(array $overrides = []): array
{
    $product = test()->product;

    return [
        'source_id' => DB::table('sale_sources')->where('business_id', test()->a->business->id)->value('id'),
        'delivery_location' => 'In store',
        'walk_in_customer_name' => 'Walk-in',
        'walk_in_customer_phone' => '0700000000',
        'payment_method' => 'cash',
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 120]],
        ...$overrides,
    ];
}

function tillInvoice(int $number): string
{
    $shop = DB::table('shops')->where('id', test()->register->shop_id)->value('code');
    $code = DB::table('registers')->where('id', test()->register->id)->value('code');

    return 'INV-'.strtoupper($shop).'-'.$code.'-2026-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
}

function pushInA(Closure $callback): mixed
{
    return app(TenantContext::class)->run(test()->a->business, $callback);
}

it('records a till\'s sale with its own time, prices and invoice number, and numbers later sales past it', function () {
    $item = pushItem('sale', pushedSale(['invoice_number' => tillInvoice(5)]));

    pushAsTill($this->token, [$item])->assertOk()
        ->assertJsonPath('data.results.0.status', 'applied')
        ->assertJsonPath('data.results.0.result.invoice_number', tillInvoice(5))
        ->assertJsonPath('meta.applied', 1);

    $sale = pushInA(fn () => Sale::query()->where('client_uuid', $item['client_uuid'])->firstOrFail());
    expect($sale->created_at->toDateTimeString())->toBe('2026-10-26 10:00:00')
        ->and($sale->completed_at->toDateTimeString())->toBe('2026-10-26 10:00:00')
        ->and((float) $sale->total_amount)->toBe(120.0)
        ->and($sale->created_by)->toBe($this->a->owner->id);

    // The next sale numbered online on this till carries on past the till's number.
    $next = pushInA(fn () => DB::transaction(fn () => app(InvoiceNumberService::class)->next($this->register->shop_id, $this->register->id)));
    expect($next)->toBe(tillInvoice(6));
});

it('applies a batch pushed twice once, answering the second time with the first result', function () {
    $items = [pushItem('sale', pushedSale()), pushItem('sale', pushedSale())];

    $first = pushAsTill($this->token, $items)->assertOk();
    $second = pushAsTill($this->token, $items)->assertOk();

    expect(collect($second->json('data.results'))->pluck('status')->all())->toBe(['duplicate', 'duplicate'])
        ->and($second->json('data.results.0.result.sale_uuid'))->toBe($first->json('data.results.0.result.sale_uuid'))
        ->and(pushInA(fn () => Sale::query()->whereIn('client_uuid', array_column($items, 'client_uuid'))->count()))->toBe(2);
});

it('refuses a sale recorded outside every lease, keeps nothing, and carries on with the rest', function () {
    $items = [
        pushItem('sale', pushedSale(), '2026-10-26 08:00:00'),
        pushItem('sale', pushedSale(), '2026-10-30 12:00:00'),
        pushItem('sale', pushedSale()),
    ];

    $response = pushAsTill($this->token, $items)->assertOk();

    expect(collect($response->json('data.results'))->pluck('status')->all())->toBe(['refused', 'refused', 'applied'])
        ->and($response->json('data.results.0.code'))->toBe(ApiErrorCode::OutsideTradingLease->value)
        ->and(pushInA(fn () => Sale::query()->where('client_uuid', $items[0]['client_uuid'])->exists()))->toBeFalse();

    // Refusals aren't kept: the same item can be pushed again.
    expect(pushAsTill($this->token, [$items[0]])->json('data.results.0.status'))->toBe('refused');
});

it('refuses an invoice number outside the till\'s series, or one already taken', function () {
    pushAsTill($this->token, [pushItem('sale', pushedSale(['invoice_number' => 'INV-XX-R9-2026-000001']))])
        ->assertJsonPath('data.results.0.code', ApiErrorCode::InvalidInvoiceNumber->value);

    pushAsTill($this->token, [pushItem('sale', pushedSale(['invoice_number' => tillInvoice(7)]))])->assertJsonPath('data.results.0.status', 'applied');
    pushAsTill($this->token, [pushItem('sale', pushedSale(['invoice_number' => tillInvoice(7)]))])
        ->assertJsonPath('data.results.0.code', ApiErrorCode::InvoiceNumberTaken->value);
});

it('refuses a sale the flat API would refuse, with the field errors', function () {
    pushAsTill($this->token, [pushItem('sale', pushedSale(['items' => []]))])
        ->assertJsonPath('data.results.0.status', 'refused')
        ->assertJsonPath('data.results.0.code', ApiErrorCode::ValidationFailed->value)
        ->assertJsonStructure(['data' => ['results' => [['errors' => ['items']]]]]);
});

it('flags a sale that takes stock below zero, and accepts it', function () {
    pushAsTill($this->token, [pushItem('sale', pushedSale(['items' => [['product_id' => $this->product->id, 'quantity' => 9999, 'price' => 1]]]))])
        ->assertJsonPath('data.results.0.status', 'applied')
        ->assertJsonPath('data.results.0.result.flags', ['negative_stock']);
});

it('saves a customer created offline, flags one whose phone is known, and links a sale to it in the same batch', function () {
    $phone = pushInA(fn () => $this->a->customer->phone);
    $new = pushItem('customer', ['name' => 'Wanjiku', 'phone' => '0711000111']);
    $twin = pushItem('customer', ['name' => 'Same phone', 'phone' => $phone]);
    $sale = pushItem('sale', pushedSale(['customer_client_uuid' => $new['client_uuid']]));

    $response = pushAsTill($this->token, [$new, $twin, $sale])->assertOk();

    expect(collect($response->json('data.results'))->pluck('status')->all())->toBe(['applied', 'applied', 'applied'])
        ->and($response->json('data.results.1.result.flags'))->toBe(['possible_duplicate'])
        ->and($response->json('data.results.1.result.possible_duplicate_of'))->toBe((string) $this->a->customer->uuid);

    $customer = pushInA(fn () => Customer::query()->where('client_uuid', $new['client_uuid'])->firstOrFail());
    expect(pushInA(fn () => Sale::query()->where('client_uuid', $sale['client_uuid'])->value('customer_id')))->toBe($customer->id)
        ->and($customer->allow_credit)->toBeFalse();
});

it('records a payment against a sale made on the till, and refuses one on a paid sale', function () {
    $cod = pushItem('sale', pushedSale(['is_cod' => true]));
    $payment = pushItem('sale_payment', ['sale_client_uuid' => $cod['client_uuid'], 'amount' => 120, 'payment_method' => 'mobile_money', 'reference' => 'QWE123'], '2026-10-26 10:30:00');

    pushAsTill($this->token, [$cod, $payment])->assertOk()
        ->assertJsonPath('data.results.1.status', 'applied')
        ->assertJsonPath('data.results.1.result.payment_status', 'paid');

    pushAsTill($this->token, [pushItem('sale_payment', ['sale_client_uuid' => $cod['client_uuid'], 'amount' => 5, 'payment_method' => 'cash'])])
        ->assertJsonPath('data.results.0.code', ApiErrorCode::SaleAlreadyPaid->value);
});

it('opens and closes a cash session with the till\'s times, and puts its sales in it', function () {
    $open = pushItem('cash_session', ['action' => 'open', 'opening_balance' => 1000], '2026-10-26 09:30:00');
    $sale = pushItem('sale', pushedSale(['cash_session' => $open['client_uuid']]));
    $close = pushItem('cash_session', ['action' => 'close', 'session' => $open['client_uuid'], 'closing_balance' => 1120], '2026-10-26 10:45:00');

    pushAsTill($this->token, [$open, $sale, $close])->assertOk()
        ->assertJsonPath('data.results.2.result.status', 'closed')
        ->assertJsonPath('data.results.2.result.variance', '0.00');

    $session = pushInA(fn () => CashRegister::query()->where('client_uuid', $open['client_uuid'])->firstOrFail());
    expect($session->opened_at->toDateTimeString())->toBe('2026-10-26 09:30:00')
        ->and($session->closed_at->toDateTimeString())->toBe('2026-10-26 10:45:00')
        ->and($session->register_id)->toBe($this->register->id)
        ->and(pushInA(fn () => Sale::query()->where('client_uuid', $sale['client_uuid'])->value('register_id')))->toBe($session->id);
});

it('audits every till sign-in, keeps a lockout that needs a manager, and lets only those allowed unlock it', function () {
    $cashier = $this->a->cashier;
    pushInA(fn () => app(ManageTillPin::class)->set($cashier, '4071'));
    $this->tenant->forget();

    $items = [
        pushItem('till_sign_in', ['user' => (string) $cashier->uuid, 'outcome' => 'failed', 'failed_attempts' => 1]),
        pushItem('till_sign_in', ['user' => (string) $cashier->uuid, 'outcome' => 'locked_out', 'failed_attempts' => 4, 'lock_minutes' => 15]),
        pushItem('till_sign_in', ['user' => (string) $cashier->uuid, 'outcome' => 'locked_out_until_unlocked', 'failed_attempts' => 12]),
    ];
    pushAsTill($this->token, $items)->assertOk();

    expect(pushInA(fn () => TillPin::query()->where('user_id', $cashier->id)->value('locked_at')))->not->toBeNull();

    // The lock reaches the till's next pull.
    Till::freshRequest();
    $staff = collect($this->withToken($this->token)->getJson('/api/v1/sync/pull')->json('data.staff'))->firstWhere('uuid', (string) $cashier->uuid);
    expect($staff['pin_locked'])->toBeTrue();

    // A cashier may not unlock; the owner may.
    $other = pushInA(function () {
        $user = User::factory()->memberOf($this->a->business)->create();
        $user->assignRole('cashier');
        $user->shops()->sync([$this->register->shop_id]);

        return $user;
    });
    pushAsTill($this->token, [pushItem('till_sign_in', ['user' => (string) $cashier->uuid, 'outcome' => 'unlocked', 'unlocked_by' => (string) $other->uuid])])
        ->assertJsonPath('data.results.0.code', ApiErrorCode::Forbidden->value);
    pushAsTill($this->token, [pushItem('till_sign_in', ['user' => (string) $cashier->uuid, 'outcome' => 'unlocked', 'unlocked_by' => (string) $this->a->owner->uuid])])
        ->assertJsonPath('data.results.0.status', 'applied');

    expect(pushInA(fn () => TillPin::query()->where('user_id', $cashier->id)->value('locked_at')))->toBeNull();

    $audit = DB::connection('audit')->table('audit_logs')->where('business_id', $this->a->business->id)
        ->where('auditable_type', User::class)->where('auditable_id', $cashier->id)->where('tags', 'like', '%till_pin%')
        ->orderBy('id')->get(['event', 'status', 'new_values']);
    expect($audit->pluck('event')->all())->toBe(['failed_login', 'failed_login', 'failed_login', 'updated'])
        ->and((string) $audit[0]->new_values)->toContain('10:00:00')->toContain((string) $this->register->uuid);
});

it('lets a manager unlock a person from the person\'s page too', function () {
    $cashier = $this->a->cashier;
    pushInA(function () use ($cashier) {
        app(ManageTillPin::class)->set($cashier, '4071');
        app(ManageTillPin::class)->lock($cashier, $this->register->id);
    });
    $this->tenant->forget();

    $this->actingAs($this->a->owner)->get(route('users.show', $cashier))->assertSee('Unlock till sign-in');
    $this->actingAs($this->a->owner)->post(route('users.till-pin.unlock', $cashier))->assertRedirect();

    expect(pushInA(fn () => app(ManageTillPin::class)->isLocked($cashier)))->toBeFalse();
});

it('refuses an unknown item, and a payment, cashier or customer of another business', function () {
    $bSale = app(TenantContext::class)->run($this->fixture->b->business, fn () => $this->fixture->b->sale);

    $response = pushAsTill($this->token, [
        pushItem('refund', []),
        pushItem('sale_payment', ['sale' => (string) $bSale->uuid, 'amount' => 1, 'payment_method' => 'cash']),
        pushItem('sale', pushedSale(['cashier' => (string) $this->fixture->b->owner->uuid])),
        pushItem('sale', pushedSale(['customer_id' => $this->fixture->b->customer->id])),
    ])->assertOk();

    expect(collect($response->json('data.results'))->pluck('code')->all())->toBe([
        ApiErrorCode::UnknownItemType->value,
        ApiErrorCode::NotFound->value,
        ApiErrorCode::ValidationFailed->value,
        ApiErrorCode::ValidationFailed->value,
    ]);
    expect(app(TenantContext::class)->run($this->fixture->b->business, fn () => $bSale->fresh()->payment_status))->toBe($bSale->payment_status);
});

it('refuses a token that is not bound to a till', function () {
    pushAsTill(Till::signIn($this->a->owner), [pushItem('sale', pushedSale())])->assertForbidden()
        ->assertJsonPath('code', ApiErrorCode::RegisterTokenRequired->value);
});
