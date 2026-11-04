<?php

use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\PaymentRequest;
use App\Models\RefundDue;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SyncIssue;
use App\Models\User;
use App\Services\Mpesa\TillPayments;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Till;
use Tests\Support\TwoBusinesses;

/*
 * Held till items (stage 10, step 5c): a refusal classed `hold` goes on the
 * till sync review with the raw item; a manager accepts it (with the fix its
 * code allows), re-rings a sale whose number was taken as a new web sale, or
 * discards it; the till learns either way. A till may discard its own items;
 * duplicate and late M-Pesa payments are owed back.
 */

beforeEach(function () {
    migrateAuditDatabase();
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));
    config(['sync.min_version.dispositions' => '2.0.0']);
    $this->fixture = TwoBusinesses::create();
    $this->a = $this->fixture->a;
    $this->tenant = app(TenantContext::class);
    $this->in = fn (Closure $work) => $this->tenant->run($this->a->business, $work);
    $this->shop = $this->a->shops[0];
    $this->register = ($this->in)(fn () => Register::defaultFor($this->shop->id));
    $this->session = ($this->in)(fn () => CashRegister::factory()->create(['shop_id' => $this->shop->id, 'register_id' => $this->register->id, 'user_id' => $this->a->owner->id, 'opened_at' => now()->subHour()]));
    $this->manager = ($this->in)(function () {
        $user = User::factory()->memberOf($this->a->business)->create();
        $user->assignRole('manager');   // sync-issues.accept and .discard, no sales.void
        $user->shops()->attach($this->shop->id);

        return $user;
    });
    $this->tenant->forget();
    $this->token = Till::token($this->a->owner, $this->register);
    $this->travel(1)->hours();
    $this->sourceId = ($this->in)(fn () => DB::table('sale_sources')->where('business_id', $this->a->business->id)->value('id'));
    $this->tillNumber = fn (int $n) => sprintf('INV-MAIN-%s-2026-%06d', $this->register->code, $n);
    $this->saleData = fn (array $overrides = []) => [
        'source_id' => $this->sourceId, 'delivery_location' => 'In store', 'walk_in_customer_name' => 'Walk-in', 'walk_in_customer_phone' => '0700000000',
        'payment_method' => 'cash', 'items' => [['product_id' => $this->a->product->id, 'quantity' => 1, 'price' => 120]], ...$overrides,
    ];
    $this->push = function (string $type, array $data, ?string $recordedAt = null, ?string $clientUuid = null) {
        Till::freshRequest();

        return $this->withToken($this->token)->withHeader('X-App-Version', '2.0.0')->postJson('/api/v1/sync/push', ['items' => [[
            'client_uuid' => $clientUuid ?? (string) Str::uuid(), 'type' => $type, 'recorded_at' => $recordedAt ?? now()->toDateTimeString(), 'data' => $data,
        ]]]);
    };
    $this->held = fn (): SyncIssue => ($this->in)(fn () => SyncIssue::query()->where('type', SyncIssue::HELD_ITEM)->whereNull('resolved_at')->latest('id')->firstOrFail());
    $this->review = fn (User $user, string $action, SyncIssue $issue, array $data = []) => $this->actingAs($user)->post(route("sync-issues.{$action}", $issue), $data);
});

it('holds a sale recorded outside its lease; accepted, it keeps the till\'s number, is flagged, and the till clears it', function () {
    $clientUuid = (string) Str::uuid();
    ($this->push)('sale', ($this->saleData)(['invoice_number' => ($this->tillNumber)(1)]), '2026-10-25 15:00:00', $clientUuid)
        ->assertJsonPath('data.results.0.code', 'outside_trading_lease')->assertJsonPath('data.results.0.disposition', 'hold');
    $issue = ($this->held)();
    expect($issue->details['item']['invoice_number'])->toBe(($this->tillNumber)(1));

    $this->tenant->forget();
    ($this->review)($this->a->owner, 'accept', $issue)->assertSessionHas('success');
    ($this->review)($this->a->owner, 'accept', $issue)->assertSessionHas('error');

    ($this->in)(function () use ($clientUuid): void {
        $sale = Sale::query()->where('client_uuid', $clientUuid)->sole();
        expect($sale->invoice_number)->toBe(($this->tillNumber)(1))
            ->and($sale->sync_flags)->toBe(['accepted_outside_lease' => true])
            ->and($sale->created_at->toDateString())->toBe('2026-10-25');
    });
    ($this->push)('sale', ($this->saleData)(), '2026-10-25 15:00:00', $clientUuid)->assertJsonPath('data.results.0.status', 'applied');
    $this->travel(2)->seconds(); // the pull reads up to a second ago
    Till::freshRequest();
    expect(collect($this->withToken($this->token)->getJson('/api/v1/sync/pull')->json('data.resolved_items'))->pluck('client_uuid'))->toContain($clientUuid);
});

it('can\'t accept a sale whose number was taken; discarding records it all; re-ringing makes one web sale that items behind it reach', function () {
    ($this->in)(fn () => Sale::factory()->create(['shop_id' => $this->shop->id, 'invoice_number' => ($this->tillNumber)(5)]));
    $clientUuid = (string) Str::uuid();
    ($this->push)('sale', ($this->saleData)(['invoice_number' => ($this->tillNumber)(5)]), null, $clientUuid)->assertJsonPath('data.results.0.code', 'invoice_number_taken');
    $issue = ($this->held)();

    ($this->review)($this->a->owner, 'accept', $issue)->assertSessionHas('error', "This item can't be accepted, only discarded.");
    $cashier = $this->a->cashier;
    ($this->review)($cashier, 'rerung', $issue)->assertForbidden();
    ($this->review)($this->manager, 'rerung', $issue)->assertRedirect();

    ($this->in)(function () use ($clientUuid, $issue): void {
        $sale = Sale::query()->where('client_uuid', $clientUuid)->sole();
        expect($sale->invoice_number)->toBe('INV-MAIN-W-2026-000001')
            ->and($sale->sync_flags['rerung'])->toEqual(['invoice_number' => ($this->tillNumber)(5), 'client_uuid' => $clientUuid]) // MySQL's JSON column reorders keys
            ->and($issue->fresh()->resolution)->toBe('rerung')
            ->and($issue->fresh()->details['outcome']['new_sale'])->toBe($sale->uuid)
            ->and(AuditLog::query()->get()->contains(fn ($row) => in_array('rerung', $row->tags ?? [], true)))->toBeTrue();
    });
    ($this->review)($this->manager, 'rerung', $issue)->assertSessionHas('error');

    // The original item answers discarded; a void queued behind it voids the new sale.
    ($this->push)('sale', ($this->saleData)(['invoice_number' => ($this->tillNumber)(5)]), null, $clientUuid)->assertJsonPath('data.results.0.status', 'discarded');
    ($this->push)('sale_void', ['sale_client_uuid' => $clientUuid, 'reason' => 'Wrong', 'voided_by' => $this->a->owner->uuid])->assertJsonPath('data.results.0.status', 'applied');
    ($this->in)(fn () => expect(Sale::query()->where('client_uuid', $clientUuid)->sole()->status)->toBe('voided'));
});

it('only discards a till sale numbered in the web series', function () {
    ($this->push)('sale', ($this->saleData)(['invoice_number' => 'INV-MAIN-W-2026-000009']))->assertJsonPath('data.results.0.code', 'invalid_invoice_number');
    $issue = ($this->held)();

    ($this->review)($this->a->owner, 'accept', $issue)->assertSessionHas('error');
    ($this->review)($this->a->owner, 'discard', $issue, ['reason' => 'Not ours'])->assertSessionHas('success');
    ($this->review)($this->a->owner, 'discard', $issue, ['reason' => 'Again'])->assertSessionHas('error');
});

it('holds a void recorded outside its lease; accepted, it voids when the window allows it now, else only discard is offered', function () {
    $sale = ($this->in)(fn () => Sale::factory()->create(['shop_id' => $this->shop->id, 'register_id' => $this->session->id, 'status' => 'completed', 'completed_at' => now(), 'paid_amount' => 0]));
    ($this->push)('sale_void', ['sale' => $sale->uuid, 'reason' => 'Wrong', 'voided_by' => $this->a->owner->uuid], '2026-10-25 15:00:00')->assertJsonPath('data.results.0.disposition', 'hold');
    $issue = ($this->held)();

    ($this->review)($this->a->owner, 'accept', $issue)->assertSessionHas('success');
    ($this->in)(fn () => expect($sale->fresh()->status)->toBe('voided'));

    $other = ($this->in)(fn () => Sale::factory()->create(['shop_id' => $this->shop->id, 'register_id' => $this->session->id, 'status' => 'completed', 'completed_at' => now(), 'paid_amount' => 0]));
    ($this->in)(fn () => $this->session->update(['status' => 'closed', 'closed_at' => now()]));
    ($this->push)('sale_void', ['sale' => $other->uuid, 'reason' => 'Wrong', 'voided_by' => $this->a->owner->uuid], '2026-10-25 15:00:00');
    $this->actingAs($this->a->owner)->get(route('sync-issues.index'))->assertOk()->assertSee('can&#039;t be voided now', false);
    ($this->review)($this->a->owner, 'accept', ($this->held)())->assertSessionHas('error');
});

it('resolves validation failures by their group: a correction, accept as recorded, or discard only', function () {
    // Correctable: a customer that doesn't exist, corrected by picking one.
    $clientUuid = (string) Str::uuid();
    ($this->push)('sale', ($this->saleData)(['customer_id' => 999999]), null, $clientUuid)->assertJsonPath('data.results.0.code', 'validation_failed');
    ($this->review)($this->a->owner, 'accept', ($this->held)(), ['customer_id' => $this->a->customer->id])->assertSessionHas('success');
    ($this->in)(fn () => expect(Sale::query()->where('client_uuid', $clientUuid)->sole()->customer_id)->toBe($this->a->customer->id));

    // Accept as recorded: a 12,000 delivery fee.
    $feeUuid = (string) Str::uuid();
    ($this->push)('sale', ($this->saleData)(['delivery_fee' => 12000]), null, $feeUuid);
    $this->actingAs($this->a->owner)->get(route('sync-issues.index'))->assertSee('Accept as recorded');
    ($this->review)($this->a->owner, 'accept', ($this->held)())->assertSessionHas('success');
    ($this->in)(fn () => expect(Sale::query()->where('client_uuid', $feeUuid)->sole()->sync_flags)->toBe(['large_amount' => true]));

    // Not acceptable: a discount above the subtotal.
    ($this->push)('sale', ($this->saleData)(['discount_amount' => 5000]));
    ($this->review)($this->a->owner, 'accept', ($this->held)())->assertSessionHas('error');
});

it('needs sales.void to discard a sale and sync-issues.accept to accept', function () {
    ($this->push)('sale', ($this->saleData)(['invoice_number' => 'INV-MAIN-W-2026-000009']));
    $issue = ($this->held)();

    ($this->review)($this->manager, 'discard', $issue, ['reason' => 'x'])->assertForbidden();
    ($this->review)($this->a->cashier, 'accept', $issue)->assertForbidden();
    ($this->review)($this->a->owner, 'discard', $issue, ['reason' => 'x'])->assertSessionHas('success');
    expect(AuditLog::query()->get()->contains(fn ($row) => in_array('held_item', $row->tags ?? [], true) && in_array('discarded', $row->tags ?? [], true)))->toBeTrue();
});

it('lets a till discard its own items: by permission per item type, recorded, and a no-op for an item already applied', function () {
    $discard = fn (string $type, string $clientUuid, string $by) => ($this->push)('outbox_item_discarded', ['item_type' => $type, 'client_uuid' => $clientUuid, 'reason' => 'Stuck', 'discarded_by' => $by, 'item' => ['note' => 'raw']]);

    ($discard)('sale_void', (string) Str::uuid(), $this->a->cashier->uuid)->assertJsonPath('data.results.0.code', 'forbidden')->assertJsonPath('data.results.0.disposition', 'drop');
    ($discard)('sale_void', $void = (string) Str::uuid(), $this->manager->uuid)->assertJsonPath('data.results.0.status', 'applied');
    ($discard)('sale', (string) Str::uuid(), $this->manager->uuid)->assertJsonPath('data.results.0.code', 'forbidden');
    ($discard)('sale_payment', $payment = (string) Str::uuid(), $this->a->owner->uuid)->assertJsonPath('data.results.0.status', 'applied');

    // The discarded void answers discarded if pushed; an item already applied can't be discarded.
    ($this->push)('sale_void', ['sale_client_uuid' => (string) Str::uuid(), 'reason' => 'x'], null, $void)->assertJsonPath('data.results.0.status', 'discarded');
    $applied = (string) Str::uuid();
    ($this->push)('sale', ($this->saleData)(), null, $applied)->assertJsonPath('data.results.0.status', 'applied');
    ($discard)('sale', $applied, $this->a->owner->uuid)->assertJsonPath('data.results.0.result.already_applied', true);

    ($this->in)(fn () => expect(SyncIssue::query()->where('type', SyncIssue::OUTBOX_ITEM_DISCARDED)->pluck('details')->pluck('client_uuid')->all())->toContain($void, $payment));
});

it('owes a payment pushed for a sale already paid once, answering drop', function () {
    $sale = ($this->in)(fn () => Sale::factory()->create(['shop_id' => $this->shop->id, 'payment_status' => 'paid', 'client_uuid' => (string) Str::uuid()]));
    $payment = ['sale' => $sale->uuid, 'amount' => 120, 'payment_method' => 'mobile_money', 'reference' => 'QX123'];

    ($this->push)('sale_payment', $payment)->assertJsonPath('data.results.0.code', 'sale_already_paid')->assertJsonPath('data.results.0.disposition', 'drop');
    ($this->push)('sale_payment', $payment);

    ($this->in)(fn () => expect(RefundDue::query()->where('source', 'duplicate_payment')->sole()->only(['method', 'reference']))->toBe(['method' => 'mobile_money', 'reference' => 'QX123']));
});

it('owes an M-Pesa payment confirmed for a sale voided meanwhile', function () {
    $sale = ($this->in)(fn () => Sale::factory()->create(['shop_id' => $this->shop->id, 'status' => 'voided', 'client_uuid' => (string) Str::uuid()]));
    ($this->in)(function () use ($sale): void {
        $request = PaymentRequest::create([
            'uuid' => (string) Str::uuid(), 'client_uuid' => (string) Str::uuid(), 'shop_id' => $this->shop->id, 'register_id' => $this->register->id,
            'requested_by' => $this->a->owner->id, 'method' => 'stk', 'shortcode' => '174379', 'sale_client_uuid' => $sale->client_uuid, 'phone' => '254700000000',
            'amount' => 300, 'paid_amount' => 300, 'status' => PaymentRequest::CONFIRMED, 'mpesa_receipt' => 'LATE1', 'confirmed_at' => now(),
        ]);
        app(TillPayments::class)->apply($request);
        app(TillPayments::class)->apply($request->fresh());

        expect(RefundDue::query()->where('source', 'late_payment')->sole()->only(['method', 'reference']))->toBe(['method' => 'mobile_money', 'reference' => 'LATE1'])
            ->and($sale->fresh()->payments()->count())->toBe(0);
    });
});
