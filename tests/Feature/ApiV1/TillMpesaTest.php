<?php

use App\Contracts\MpesaGateway;
use App\Enums\ApiErrorCode;
use App\Models\BusinessIntegration;
use App\Models\PaymentRequest;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SyncIssue;
use App\Services\Mpesa\FakeMpesaGateway;
use App\Services\Mpesa\MpesaPayment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Till;
use Tests\Support\TwoBusinesses;

/**
 * M-Pesa at the till, Phase 7 (step 9; decision 4.9, open question 2): an STK
 * Push goes out through the shop's own number; a payment counts only when the
 * M-Pesa API, queried, says so, and pays its sale exactly once, even when the
 * till pushes the sale afterwards; a code entered on an offline till is
 * matched by code and amount, or stays pending and goes on the review.
 */
beforeEach(function () {
    migrateAuditDatabase();
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));
    $this->gateway = new FakeMpesaGateway;
    app()->instance(MpesaGateway::class, $this->gateway);

    $this->fixture = TwoBusinesses::create();
    $this->tenant = app(TenantContext::class);
    $this->a = $this->fixture->a;
    $this->register = $this->tenant->run($this->a->business, function (): Register {
        $shop = $this->a->product->shop()->firstOrFail();
        $shop->forceFill(['mpesa_shortcode' => '174379', 'mpesa_shortcode_type' => 'till', 'mpesa_verified_at' => now()])->save();
        BusinessIntegration::create(['shop_id' => $shop->id, 'type' => BusinessIntegration::TYPE_MPESA, 'credentials' => ['consumer_key' => 'k', 'consumer_secret' => 's'], 'status' => BusinessIntegration::STATUS_ACTIVE, 'verified_at' => now()]);

        return Register::defaultFor($shop->id);
    });
    $this->tenant->forget();
    $this->token = Till::token($this->a->owner, $this->register);
    $this->travel(1)->hours();
});

function stkAsTill(string $token, array $data)
{
    Till::freshRequest();

    return test()->withToken($token)->postJson('/api/v1/mpesa/stk', $data);
}

function checkAsTill(string $token, string $uuid)
{
    Till::freshRequest();

    return test()->withToken($token)->getJson("/api/v1/mpesa/requests/{$uuid}");
}

function mpesaPush(string $token, array $items)
{
    Till::freshRequest();

    return test()->withToken($token)->postJson('/api/v1/sync/push', ['items' => $items]);
}

function awaitingSale(string $clientUuid, float $price = 500): array
{
    return ['client_uuid' => $clientUuid, 'type' => 'sale', 'recorded_at' => '2026-10-26 09:30:00', 'data' => [
        'source_id' => DB::table('sale_sources')->where('business_id', test()->a->business->id)->value('id'),
        'delivery_location' => 'In store', 'walk_in_customer_name' => 'Walk-in', 'walk_in_customer_phone' => '0712345678',
        'payment_method' => 'mobile_money', 'awaiting_mpesa' => true,
        'items' => [['product_id' => test()->a->product->id, 'quantity' => 1, 'price' => $price]],
    ]];
}

function saleByClient(string $clientUuid): ?Sale
{
    return app(TenantContext::class)->run(test()->a->business, fn () => Sale::query()->where('client_uuid', $clientUuid)->first());
}

it('sends an STK Push through the shop\'s own number, once per client uuid', function () {
    $request = ['client_uuid' => (string) Str::uuid(), 'phone' => '0712 345 678', 'amount' => 500];

    $first = stkAsTill($this->token, $request)->assertOk()->assertJsonPath('data.status', 'pending')->json('data.uuid');
    $again = stkAsTill($this->token, $request)->assertOk()->json('data.uuid');

    expect($again)->toBe($first)
        ->and($this->gateway->pushes)->toHaveCount(1)
        ->and($this->gateway->pushes[0]['shortcode'])->toBe('174379')
        ->and($this->gateway->pushes[0]['phone'])->toBe('254712345678');
});

it('pays the sale once M-Pesa confirms, and never twice', function () {
    $saleUuid = (string) Str::uuid();
    mpesaPush($this->token, [awaitingSale($saleUuid)])->assertJsonPath('data.results.0.result.payment_status', 'unpaid');
    $request = stkAsTill($this->token, ['client_uuid' => (string) Str::uuid(), 'sale_client_uuid' => $saleUuid, 'phone' => '0712345678', 'amount' => 500])->json('data');

    // Not believed until M-Pesa says so.
    checkAsTill($this->token, $request['uuid'])->assertJsonPath('data.status', 'pending');
    expect(saleByClient($saleUuid)->payment_status)->toBe('unpaid');

    $checkout = $this->tenant->run($this->a->business, fn () => PaymentRequest::query()->where('uuid', $request['uuid'])->value('checkout_request_id'));
    $this->gateway->settle($checkout, MpesaPayment::paid('QKA1B2C3D4', '500.00'));

    checkAsTill($this->token, $request['uuid'])->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.applied', true);
    checkAsTill($this->token, $request['uuid']);
    $this->artisan('mpesa:check-pending')->assertSuccessful();

    $sale = saleByClient($saleUuid);
    expect($sale->payment_status)->toBe('paid')
        ->and($this->tenant->run($this->a->business, fn () => $sale->payments()->count()))->toBe(1)
        ->and($this->tenant->run($this->a->business, fn () => $sale->payments()->value('reference')))->toBe('QKA1B2C3D4');
});

it('pays a sale the till pushes only after the payment was confirmed, and tells an offline till in its pull', function () {
    $saleUuid = (string) Str::uuid();
    $request = stkAsTill($this->token, ['client_uuid' => (string) Str::uuid(), 'sale_client_uuid' => $saleUuid, 'phone' => '0712345678', 'amount' => 500])->json('data');
    $checkout = $this->tenant->run($this->a->business, fn () => PaymentRequest::query()->where('uuid', $request['uuid'])->value('checkout_request_id'));
    $this->gateway->settle($checkout, MpesaPayment::paid('QKB9X8Y7Z6', '500.00'));

    // The till went offline: the scheduler confirms it.
    $this->artisan('mpesa:check-pending')->assertSuccessful();
    $this->travel(2)->seconds();
    Till::freshRequest();
    $pulled = collect($this->withToken($this->token)->getJson('/api/v1/sync/pull')->json('data.payment_requests'))->firstWhere('uuid', $request['uuid']);
    expect($pulled['status'])->toBe('confirmed')->and($pulled['applied'])->toBeFalse();

    mpesaPush($this->token, [awaitingSale($saleUuid)])->assertJsonPath('data.results.0.result.payment_status', 'paid');
    expect($this->tenant->run($this->a->business, fn () => saleByClient($saleUuid)->payments()->count()))->toBe(1);
});

it('refuses an STK Push for a shop with no M-Pesa number, and notes one M-Pesa refused or the customer let lapse', function () {
    $this->tenant->run($this->a->business, fn () => DB::table('shops')->where('id', $this->register->shop_id)->update(['mpesa_verified_at' => null]));
    stkAsTill($this->token, ['client_uuid' => (string) Str::uuid(), 'phone' => '0712345678', 'amount' => 500])
        ->assertStatus(422)->assertJsonPath('code', ApiErrorCode::MpesaNotSetUp->value);

    $this->tenant->run($this->a->business, fn () => DB::table('shops')->where('id', $this->register->shop_id)->update(['mpesa_verified_at' => now()]));
    $this->gateway->refuseStk('Invalid phone');
    stkAsTill($this->token, ['client_uuid' => (string) Str::uuid(), 'phone' => '0712345678', 'amount' => 500])->assertJsonPath('data.status', 'failed');

    $fresh = new FakeMpesaGateway;
    app()->instance(MpesaGateway::class, $fresh);
    $lapsed = stkAsTill($this->token, ['client_uuid' => (string) Str::uuid(), 'phone' => '0712345678', 'amount' => 500])->json('data.uuid');
    $this->travel(31)->minutes();
    $this->artisan('mpesa:check-pending')->assertSuccessful();
    expect($this->tenant->run($this->a->business, fn () => PaymentRequest::query()->where('uuid', $lapsed)->value('status')))->toBe('failed');
});

it('matches a code entered on an offline till by code and amount, and pays its sale', function () {
    $saleUuid = (string) Str::uuid();
    $this->gateway->recordTransaction('174379', MpesaPayment::paid('QKC1D2E3F4', '500.00'));

    $result = mpesaPush($this->token, [
        awaitingSale($saleUuid),
        ['client_uuid' => (string) Str::uuid(), 'type' => 'mpesa_code', 'recorded_at' => '2026-10-26 09:31:00', 'data' => ['mpesa_receipt' => 'qkc1d2e3f4', 'amount' => 500, 'sale_client_uuid' => $saleUuid]],
    ])->json('data.results.1');

    expect($result['result']['payment_request']['status'])->toBe('confirmed')
        ->and(saleByClient($saleUuid)->payment_status)->toBe('paid');
});

it('keeps an unknown code pending and on the review, then pays once M-Pesa knows it', function () {
    $saleUuid = (string) Str::uuid();
    mpesaPush($this->token, [
        awaitingSale($saleUuid),
        ['client_uuid' => (string) Str::uuid(), 'type' => 'mpesa_code', 'recorded_at' => '2026-10-26 09:31:00', 'data' => ['mpesa_receipt' => 'QKD5E6F7G8', 'amount' => 500, 'sale_client_uuid' => $saleUuid]],
    ])->assertJsonPath('data.results.1.result.flags', ['mpesa_pending']);

    expect($this->tenant->run($this->a->business, fn () => SyncIssue::query()->where('type', SyncIssue::MPESA_UNMATCHED)->exists()))->toBeTrue()
        ->and(saleByClient($saleUuid)->payment_status)->toBe('unpaid');

    $this->gateway->recordTransaction('174379', MpesaPayment::paid('QKD5E6F7G8', '500.00'));
    $this->travel(16)->minutes();
    $this->artisan('mpesa:check-pending')->assertSuccessful();

    expect(saleByClient($saleUuid)->payment_status)->toBe('paid');
});

it('won\'t pay with a code of another amount, a code used twice, or a code paid to another number', function () {
    $this->gateway->recordTransaction('174379', MpesaPayment::paid('QKE1E1E1E1', '300.00'));
    $this->gateway->recordTransaction('999999', MpesaPayment::paid('QKF2F2F2F2', '500.00'));
    $this->gateway->recordTransaction('174379', MpesaPayment::paid('QKG3G3G3G3', '500.00'));
    $code = fn (string $receipt) => ['client_uuid' => (string) Str::uuid(), 'type' => 'mpesa_code', 'recorded_at' => '2026-10-26 09:31:00', 'data' => ['mpesa_receipt' => $receipt, 'amount' => 500]];

    $results = mpesaPush($this->token, [$code('QKE1E1E1E1'), $code('QKF2F2F2F2'), $code('QKG3G3G3G3'), $code('QKG3G3G3G3')])->json('data.results');

    expect(array_map(fn ($r) => $r['result']['payment_request']['status'], $results))->toBe(['unmatched', 'pending', 'confirmed', 'unmatched']);
    $types = $this->tenant->run($this->a->business, fn () => SyncIssue::query()->pluck('type')->all());
    expect($types)->toContain(SyncIssue::MPESA_AMOUNT_MISMATCH)->toContain(SyncIssue::MPESA_CODE_REUSED)->toContain(SyncIssue::MPESA_UNMATCHED);
});

it('never shows a till another business\'s payment requests', function () {
    $bRequest = $this->tenant->run($this->fixture->b->business, fn () => PaymentRequest::create([
        'client_uuid' => (string) Str::uuid(), 'shop_id' => $this->fixture->b->shops[0]->id, 'method' => 'stk', 'amount' => 10, 'shortcode' => '600000',
    ]));
    $this->tenant->forget();

    checkAsTill($this->token, $bRequest->uuid)->assertNotFound();
});
