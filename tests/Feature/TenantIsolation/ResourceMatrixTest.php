<?php

use App\Http\Middleware\ResolveTenant;
use App\Models\AbandonedCart;
use App\Models\Attribute;
use App\Models\BaileysSession;
use App\Models\BusinessClosure;
use App\Models\BusinessExport;
use App\Models\BusinessInvite;
use App\Models\Campaign;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\CreditAccount;
use App\Models\DeliveryCompany;
use App\Models\EcommerceOrder;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PlatformPayment;
use App\Models\PricingRule;
use App\Models\PurchaseOrder;
use App\Models\Register;
use App\Models\Role;
use App\Models\SocialAccount;
use App\Models\SyncIssue;
use App\Services\Billing\AiCredits;
use App\Support\Tenancy\BusinessLink;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\Support\PlatformRoutes;
use Tests\Support\Till;
use Tests\Support\TwoBusinesses;

/**
 * The resource matrix (Phase 2): every resource route that names a record,
 * on the web and the flat API, refuses business B's record to business A's
 * owner (who passes every policy, so only the tenant boundary stands in the
 * way): show, update and delete are 404, and the record is unchanged.
 * `/api/v1` and sync rows are added as those endpoints are built (Phase 7: registers, sync, M-Pesa).
 *
 * Every new model, route or endpoint adds its row here.
 */
beforeEach(function () {
    // Only the owner acts here: the fixture's owner role brings every permission.
    $this->fixture = TwoBusinesses::create();
    app(TenantContext::class)->forget();
});

/**
 * Business B's record for a resource, created in B.
 */
function recordOfB(TwoBusinesses $fixture, string $resource): Model
{
    $b = $fixture->b;
    $shop = ['shop_id' => $b->shops[0]->id];

    return app(TenantContext::class)->run($b->business, fn (): Model => match ($resource) {
        'abandoned-carts' => AbandonedCart::factory()->create($shop),
        'attributes' => Attribute::factory()->create(),
        'baileys.sessions' => BaileysSession::factory()->create($shop),
        'campaigns' => Campaign::factory()->create($shop),
        'cash-registers' => CashRegister::factory()->create([...$shop, 'user_id' => $b->owner->id]),
        'categories' => Category::factory()->create(),
        'credit-accounts' => CreditAccount::factory()->create(['customer_id' => $b->customer->id, ...$shop]),
        'customers' => $b->customer,
        'delivery-companies' => DeliveryCompany::factory()->create(),
        'ecommerce-orders' => EcommerceOrder::factory()->create($shop),
        'expense-categories' => ExpenseCategory::factory()->create(),
        'expenses' => Expense::factory()->create($shop),
        'invites' => BusinessInvite::factory()->create(['email' => 'invitee@bravo-shop.co.ke']),
        'pricing-rules' => PricingRule::factory()->create(),
        'products' => $b->product,
        'purchase-orders' => PurchaseOrder::factory()->create([...$shop, 'supplier_id' => $b->supplier->id]),
        // B's own copy of a template A also has, by the same name (Phase 5).
        'roles' => Role::query()->where('business_id', $b->business->id)->where('name', 'cashier')->firstOrFail(),
        'sales' => $b->sale,
        'shops' => $b->shops[1],
        'social-accounts' => SocialAccount::factory()->create($shop),
        'suppliers' => $b->supplier,
        'sync-issues' => SyncIssue::create(['type' => SyncIssue::POSSIBLE_DUPLICATE_CUSTOMER, 'key' => 'customer:b', 'details' => ['customer' => (string) $b->customer->uuid, 'duplicate_of' => (string) $b->customer->uuid]]),
        'users' => $b->cashier,
    });
}

/** Resource => the actions its web routes offer on one record. */
const WEB_RESOURCES = [
    'abandoned-carts' => ['show', 'update'],
    'attributes' => ['show', 'update', 'destroy'],
    'baileys.sessions' => ['show', 'destroy'],
    'campaigns' => ['show', 'update', 'destroy'],
    'cash-registers' => ['show'],
    'categories' => ['show', 'update', 'destroy'],
    'credit-accounts' => ['show', 'update', 'destroy'],
    'customers' => ['show', 'update', 'destroy'],
    'ecommerce-orders' => ['show'],
    'expense-categories' => ['show', 'update', 'destroy'],
    'expenses' => ['show', 'update', 'destroy'],
    'pricing-rules' => ['show', 'update', 'destroy'],
    'products' => ['show', 'update', 'destroy'],
    'purchase-orders' => ['show', 'update', 'destroy'],
    'roles' => ['show', 'edit', 'update', 'destroy', 'permissions', 'updatePermissions', 'reset', 'active'],
    'sales' => ['show', 'update', 'destroy'],
    'shops' => ['show', 'update', 'destroy'],
    // show: SocialAccountController::show doesn't exist (already on main).
    'social-accounts' => ['update', 'destroy'],
    'suppliers' => ['show', 'update', 'destroy'],
    'users' => ['show', 'update', 'destroy', 'till-pin.reset', 'till-pin.unlock'],
    'invites' => ['resend', 'destroy', 'role'],
    'sync-issues' => ['resolve', 'merge'],
];

/** Resource => the actions its flat API routes offer on one record. */
const API_RESOURCES = [
    'abandoned-carts' => ['show', 'update'],
    'cash-registers' => ['show'],
    'categories' => ['show', 'update', 'destroy'],
    'customers' => ['show', 'update', 'destroy'],
    'delivery-companies' => ['show', 'update', 'destroy'],
    'ecommerce-orders' => ['show'],
    'expense-categories' => ['show', 'update', 'destroy'],
    'expenses' => ['show', 'update', 'destroy'],
    'products' => ['show', 'update', 'destroy'],
    'purchase-orders' => ['show', 'update', 'destroy'],
    'sales' => ['show', 'update', 'destroy'],
    'shops' => ['show', 'update', 'destroy'],
    'suppliers' => ['show', 'update', 'destroy'],
    'users' => ['show', 'update', 'destroy'],
];

/**
 * @return list<array{0: string, 1: string, 2: string}>
 */
function matrixRows(): array
{
    $rows = [];
    foreach (['web' => WEB_RESOURCES, 'api' => API_RESOURCES] as $channel => $resources) {
        foreach ($resources as $resource => $actions) {
            foreach ($actions as $action) {
                $rows["{$channel} {$resource}.{$action}"] = [$channel, $resource, $action];
            }
        }
    }

    return $rows;
}

it('refuses business B\'s record to business A', function (string $channel, string $resource, string $action) {
    $record = recordOfB($this->fixture, $resource);
    $before = $record->fresh()?->getAttributes();
    $route = ($channel === 'api' ? 'api.' : '').$resource.'.'.$action;

    $channel === 'api' ? Sanctum::actingAs($this->fixture->a->owner, ['*']) : $this->actingAs($this->fixture->a->owner);
    // The route's own method (an update may be PUT or PATCH).
    $method = strtolower(collect(Route::getRoutes()->getByName($route)->methods())->reject(fn ($m) => $m === 'HEAD')->first());
    $call = $channel === 'api' ? $method.'Json' : $method;

    $response = $this->{$call}(route($route, $record), $action === 'update' ? ['name' => 'Taken over'] : []);

    expect($response->getStatusCode())->toBe(404, "{$route} answered {$response->getStatusCode()} ".substr((string) $response->exception?->getMessage(), 0, 200));

    // Unchanged and still there (read in B, past A's scope).
    $after = app(TenantContext::class)->run($this->fixture->b->business, fn () => $record->newQueryWithoutScopes()->find($record->getKey())?->getAttributes());
    expect($after)->toBe($before);
})->with(matrixRows());

it('rejects business B\'s ids on create', function (string $route, Closure $payload) {
    $this->actingAs($this->fixture->a->owner);
    $response = $this->post(route($route), $payload($this->fixture->b));

    $response->assertSessionHasErrors();
})->with([
    'expense in B\'s shop' => ['expenses.store', fn ($b) => ['shop_id' => $b->shops[0]->id, 'title' => 'x', 'amount' => 10, 'expense_date' => now()->toDateString()]],
    'product in B\'s shop' => ['products.store', fn ($b) => ['shop_id' => $b->shops[0]->id, 'name' => 'x', 'selling_price' => 10]],
    'purchase order from B\'s supplier' => ['purchase-orders.store', fn ($b) => ['supplier_id' => $b->supplier->id, 'shop_id' => $b->shops[0]->id]],
    'sale to B\'s customer of B\'s product' => ['sales.store', fn ($b) => ['customer_id' => $b->customer->id, 'items' => [['product_id' => $b->product->id, 'quantity' => 1, 'price' => 10]]]],
]);

it('refuses to open business B, or make it the default, for business A\'s owner (stage 3)', function (string $route) {
    $owner = $this->fixture->a->owner;

    $this->actingAs($owner)->post(route($route, $this->fixture->b->business))->assertNotFound();

    expect(session(ResolveTenant::SESSION_KEY))->not->toBe($this->fixture->b->business->id)
        ->and($owner->fresh()->default_business_id)->toBe($this->fixture->a->business->id);
})->with(['businesses.switch', 'businesses.default']);

it('refuses to switch an /api/v1 token to business B for business A\'s owner (stage 3)', function () {
    $owner = $this->fixture->a->owner;
    $token = $this->postJson(route('api.v1.auth.sign-in'), ['email' => $owner->email, 'password' => 'password', 'device_name' => 'Till'])
        ->json('data.token');
    app('auth')->forgetGuards();
    app(TenantContext::class)->forget();

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson(route('api.v1.businesses.switch', $this->fixture->b->business))->assertNotFound();

    expect(PersonalAccessToken::findToken($token)?->business_id)->toBe($this->fixture->a->business->id);
});

it('refuses business B\'s export download and closure cancel to business A\'s owner (stage 3)', function () {
    $b = $this->fixture->b;
    $export = app(TenantContext::class)->run($b->business, fn () => BusinessExport::create(['status' => 'ready', 'path' => 'x.zip']));
    $closure = BusinessClosure::create([
        'business_id' => $b->business->id, 'business_uuid' => $b->business->uuid, 'business_name' => $b->business->name,
        'delete_after' => now()->addDays(30),
    ]);
    app(TenantContext::class)->forget();

    $this->actingAs($this->fixture->a->owner)
        ->get(URL::temporarySignedRoute('business.exports.download', now()->addHour(), $export))
        ->assertNotFound();
    $this->actingAs($this->fixture->a->owner)->post(route('business.closure.cancel', $closure))->assertNotFound();

    expect($closure->fresh()->cancelled_at)->toBeNull();
});

it('refuses business B\'s shop, business and people on the stage 3 routes to business A\'s owner', function () {
    $b = $this->fixture->b;
    $a = $this->fixture->a;

    // A shop's M-Pesa page and its test-query save.
    $this->actingAs($a->owner)->get(route('shops.mpesa.edit', $b->shops[0]))->assertNotFound();
    $this->actingAs($a->owner)->put(route('shops.mpesa.update', $b->shops[0]), [
        'shortcode' => '123456', 'type' => 'till', 'consumer_key' => 'k', 'consumer_secret' => 's',
    ])->assertNotFound();

    // The CSV import into a shop of another business.
    $this->actingAs($a->owner)->post(route('products.import.store'), [
        'shop_id' => $b->shops[0]->id,
        'file' => UploadedFile::fake()->createWithContent('p.csv', "name,selling_price\nX,1\n"),
    ])->assertSessionHasErrors('shop_id');

    // A signed notification link into a business the user is not a member of.
    $link = app(TenantContext::class)->run($b->business, fn () => BusinessLink::to('/customers'));
    $this->actingAs($a->owner)->get($link)->assertNotFound();

    // Handing the business over to another business's owner.
    $a->business->update(['primary_owner_id' => $a->owner->id]);
    $this->actingAs($a->owner)->post(route('business.handover.code'), ['owner' => $b->owner->uuid])->assertSessionHasErrors('owner');

    expect($b->shops[0]->fresh()->mpesa_shortcode)->toBeNull();
});

it('refuses business B\'s payment and billing page to business A\'s owner (Phase 4)', function () {
    $a = $this->fixture->a;
    $b = $this->fixture->b;

    $payment = app(TenantContext::class)->run($b->business, fn () => PlatformPayment::create([
        'purpose' => 'subscription', 'amount' => 1500, 'method' => 'mpesa', 'status' => 'pending', 'checkout_request_id' => 'ws_CO_B',
    ]));
    app(TenantContext::class)->forget();

    $this->actingAs($a->owner)->post(route('billing.payments.check', $payment))->assertNotFound();
    $this->actingAs($a->cashier)->get(route('billing.show'))->assertForbidden();

    expect($payment->fresh()->status)->toBe('pending');
});

it('refuses business B\'s billing to business A on /api/v1 (Phase 4)', function () {
    $a = $this->fixture->a;
    $b = $this->fixture->b;
    $payment = app(TenantContext::class)->run($b->business, fn () => PlatformPayment::create([
        'purpose' => 'subscription', 'amount' => 1500, 'method' => 'mpesa', 'status' => 'pending', 'checkout_request_id' => 'ws_CO_B_V1',
    ]));
    foreach ([[$a, 5], [$b, 777]] as [$side, $credits]) {
        app(TenantContext::class)->run($side->business, fn () => app(AiCredits::class)->topUp($side->business->id, $credits));
    }
    app(TenantContext::class)->forget();

    $token = $this->postJson(route('api.v1.auth.sign-in'), ['email' => $a->owner->email, 'password' => 'password', 'device_name' => 'Till'])
        ->json('data.token');
    app('auth')->forgetGuards();
    app(TenantContext::class)->forget();
    $headers = ['Authorization' => "Bearer {$token}"];

    $this->withHeaders($headers)->postJson(route('api.v1.billing.payments.check', $payment))->assertNotFound();
    // A's summary is A's own: its credit balance, not B's.
    $this->withHeaders($headers)->getJson(route('api.v1.billing.show'))->assertOk()->assertJsonPath('data.ai_credits', 5);

    // Billing is for owners only.
    $this->flushHeaders();
    app('auth')->forgetGuards();
    app(TenantContext::class)->forget();
    $cashierToken = $this->postJson(route('api.v1.auth.sign-in'), ['email' => $a->cashier->email, 'password' => 'password', 'device_name' => 'Till'])
        ->json('data.token');
    app('auth')->forgetGuards();
    app(TenantContext::class)->forget();
    $this->withHeaders(['Authorization' => "Bearer {$cashierToken}"])->getJson(route('api.v1.billing.show'))->assertForbidden();

    expect($payment->fresh()->status)->toBe('pending');
});

it('refuses business B\'s registers to business A on /api/v1 (Phase 7)', function () {
    $bRegister = app(TenantContext::class)->run($this->fixture->b->business, fn () => Register::query()->firstOrFail());
    app(TenantContext::class)->forget();

    $token = $this->postJson(route('api.v1.auth.sign-in'), ['email' => $this->fixture->a->owner->email, 'password' => 'password', 'device_name' => 'Till'])
        ->json('data.token');
    app(TenantContext::class)->forget();
    app('auth')->forgetGuards();
    $headers = ['Authorization' => "Bearer {$token}"];

    // A's list holds A's tills only.
    $listed = $this->withHeaders($headers)->getJson(route('api.v1.registers.index'))->assertOk()->json('data.*.uuid');
    expect($listed)->not->toContain($bRegister->uuid)->not->toBeEmpty();

    app(TenantContext::class)->forget();
    app('auth')->forgetGuards();
    $this->withHeaders($headers)->postJson(route('api.v1.registers.pair', $bRegister), ['device_id' => 'intruder'])->assertNotFound();

    expect($bRegister->fresh()->device_id)->toBeNull();
});

it('leases a till of business A for A\'s till only, and B\'s till for B\'s (Phase 7)', function () {
    [$aRegister, $bRegister] = array_map(
        fn ($side) => app(TenantContext::class)->run($side->business, fn () => Register::query()->firstOrFail()),
        [$this->fixture->a, $this->fixture->b],
    );

    foreach ([[$this->fixture->a, $aRegister], [$this->fixture->b, $bRegister]] as [$side, $register]) {
        $token = Till::token($side->owner, $register);
        Till::freshRequest();
        $this->withToken($token)->getJson(route('api.v1.sync.lease'))->assertOk()->assertJsonPath('meta.lease.register', $register->uuid);

        $leaseBusiness = DB::table('register_leases')->where('register_id', $register->id)->distinct()->pluck('business_id')->all();
        expect($leaseBusiness)->toBe([$side->business->id]);
    }
});

it('pulls business A\'s records and deletions to A\'s till, and none of B\'s (Phase 7)', function () {
    $aRegister = app(TenantContext::class)->run($this->fixture->a->business, fn () => Register::query()->firstOrFail());
    // A deletion in B: it must never reach A's till.
    app(TenantContext::class)->run($this->fixture->b->business, fn () => $this->fixture->b->customer->delete());
    $token = Till::token($this->fixture->a->owner, $aRegister);
    $this->travel(2)->seconds();

    Till::freshRequest();
    $body = json_encode($this->withToken($token)->getJson(route('api.v1.sync.pull'))->assertOk()->json('data'));

    foreach ([$this->fixture->b->business->uuid, $this->fixture->b->product->uuid, $this->fixture->b->customer->uuid, $this->fixture->b->owner->uuid] as $uuid) {
        expect($body)->not->toContain((string) $uuid);
    }
    expect($body)->toContain((string) $this->fixture->a->product->uuid);
});

it('applies A\'s till\'s push in A only, and reaches none of B\'s records (Phase 7)', function () {
    $aRegister = app(TenantContext::class)->run($this->fixture->a->business, fn () => Register::query()->firstOrFail());
    $bSale = $this->fixture->b->sale;
    $bPaid = $bSale->payment_status;
    $token = Till::token($this->fixture->a->owner, $aRegister);

    Till::freshRequest();
    $results = $this->withToken($token)->postJson(route('api.v1.sync.push'), ['items' => [
        ['client_uuid' => (string) Str::uuid(), 'type' => 'sale_payment', 'recorded_at' => now()->toIso8601String(), 'data' => ['sale' => (string) $bSale->uuid, 'amount' => 1, 'payment_method' => 'cash']],
        ['client_uuid' => $customerUuid = (string) Str::uuid(), 'type' => 'customer', 'recorded_at' => now()->toIso8601String(), 'data' => ['name' => 'Pushed in A', 'phone' => $this->fixture->b->customer->phone]],
    ]])->assertOk()->json('data.results');

    expect($results[0]['code'])->toBe('not_found')
        ->and($results[1]['status'])->toBe('applied')
        // B's customer with the same phone is not A's duplicate.
        ->and($results[1]['result']['flags'])->toBe([])
        ->and(app(TenantContext::class)->run($this->fixture->b->business, fn () => $bSale->fresh()->payment_status))->toBe($bPaid)
        ->and(DB::table('customers')->where('client_uuid', $customerUuid)->value('business_id'))->toBe($this->fixture->a->business->id);
});

it('refuses every platform console route to business A\'s owner (Phase 5)', function () {
    // B's records as parameters; the console is for platform operators only.
    foreach (PlatformRoutes::all($this->fixture) as [$method, $url, $name]) {
        app(TenantContext::class)->forget();
        $status = $this->actingAs($this->fixture->a->owner)->{$method}($url)->getStatusCode();

        expect($status)->toBe(404, "{$name} answered {$status}");
    }

    expect($this->fixture->b->business->fresh()->status)->toBe('active');
});
