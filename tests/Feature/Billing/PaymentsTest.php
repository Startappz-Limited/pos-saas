<?php

use App\Actions\Billing\ApplyPlatformPayment;
use App\Actions\Billing\RecordManualPayment;
use App\Actions\Business\CloseBusiness;
use App\Contracts\PaymentCollector;
use App\Exceptions\PaymentRejected;
use App\Models\AiCreditBalance;
use App\Models\Business;
use App\Models\BusinessClosure;
use App\Models\PlatformPayment;
use App\Models\Subscription;
use App\Services\Billing\CollectionResult;
use App\Services\Billing\Entitlements;
use App\Services\Billing\FakePaymentCollector;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TwoBusinesses;

/**
 * Collecting money for the platform (Phase 4, decision 4.6): payments recorded
 * by hand, and payments confirmed by querying the M-Pesa API, extend the
 * subscription exactly once and never reach the wrong business.
 */
beforeEach(function () {
    if (! Schema::connection('audit')->hasTable('audit_logs')) {
        Artisan::call('migrate', ['--database' => 'audit', '--path' => 'database/migrations/audit', '--force' => true]);
    }
    Storage::fake('tenant');
    Storage::fake('public');
    Notification::fake();
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->a = $this->fixture->a;
    $this->b = $this->fixture->b;
    $this->tenant = app(TenantContext::class);
    $this->record = app(RecordManualPayment::class);
});

function lapsedSubscription(Business $business, array $overrides = []): Subscription
{
    return subscribe($business, 'tier-1', [
        'shop_count' => 2, 'price_at_signup' => 1500, 'status' => Subscription::ACTIVE, 'current_period_end' => now()->subDays(3), ...$overrides,
    ]);
}

function periodEnd(Business $business): Carbon
{
    return app(TenantContext::class)->run($business, fn () => Subscription::query()->first()->current_period_end);
}

it('extends a lapsed subscription by the months paid, from now', function () {
    lapsedSubscription($this->a->business);

    // 2 shops at 1,500 = 3,000 a month; 6,000 buys two.
    $payment = $this->record->execute($this->a->business, PlatformPayment::SUBSCRIPTION, 6000, 'RCP1234');

    expect($payment->isApplied())->toBeTrue()
        ->and($payment->method)->toBe('manual')
        ->and(periodEnd($this->a->business)->isSameDay(now()->addMonthsNoOverflow(2)))->toBeTrue()
        ->and($this->tenant->run($this->a->business, fn () => app(Entitlements::class)->isReadOnly()))->toBeFalse();
});

it('counts from the end of what is already paid for', function () {
    $end = now()->addDays(10);
    lapsedSubscription($this->a->business, ['current_period_end' => $end]);

    $this->record->execute($this->a->business, PlatformPayment::SUBSCRIPTION, 3000);

    expect(periodEnd($this->a->business)->isSameDay($end->copy()->addMonthNoOverflow()))->toBeTrue();
});

it('keeps the rest of a trial when paying during it', function () {
    subscribe($this->a->business, 'tier-3', [
        'shop_count' => 1, 'price_at_signup' => 5000, 'status' => Subscription::TRIALING, 'trial_ends_at' => now()->addDays(10), 'current_period_end' => null,
    ]);

    $this->record->execute($this->a->business, PlatformPayment::SUBSCRIPTION, 5000);

    $subscription = $this->tenant->run($this->a->business, fn () => Subscription::query()->first());
    expect($subscription->status)->toBe('active')
        ->and($subscription->current_period_end->isSameDay(now()->addDays(10)->addMonthNoOverflow()))->toBeTrue();
});

it('applies a payment exactly once', function () {
    lapsedSubscription($this->a->business);
    $payment = $this->record->execute($this->a->business, PlatformPayment::SUBSCRIPTION, 3000);
    $end = periodEnd($this->a->business);

    expect(app(ApplyPlatformPayment::class)->execute($payment))->toBeFalse()
        ->and(periodEnd($this->a->business)->equalTo($end))->toBeTrue();
});

it('refuses a payment that does not cover a month, and keeps no record of it', function () {
    lapsedSubscription($this->a->business);

    expect(fn () => $this->record->execute($this->a->business, PlatformPayment::SUBSCRIPTION, 1000))->toThrow(PaymentRejected::class);

    expect($this->tenant->run($this->a->business, fn () => PlatformPayment::query()->count()))->toBe(0);
});

it('refuses the same M-Pesa receipt twice', function () {
    lapsedSubscription($this->a->business);
    $this->record->execute($this->a->business, PlatformPayment::SUBSCRIPTION, 3000, 'SAMERCPT');

    expect(fn () => $this->record->execute($this->a->business, PlatformPayment::SUBSCRIPTION, 3000, 'SAMERCPT'))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('buys AI credit with a top-up of at least KES 500', function () {
    lapsedSubscription($this->a->business);

    expect(fn () => $this->record->execute($this->a->business, PlatformPayment::AI_TOPUP, 300))->toThrow(PaymentRejected::class);

    $this->record->execute($this->a->business, PlatformPayment::AI_TOPUP, 500);

    // KES 1 = 10 credits by default.
    expect((float) $this->tenant->run($this->a->business, fn () => AiCreditBalance::query()->value('balance')))->toBe(5000.0);
});

it('records a payment from the command, for the platform operator', function () {
    lapsedSubscription($this->a->business);

    $this->artisan('billing:record-payment', ['business' => (string) $this->a->business->uuid, 'amount' => 3000, '--receipt' => 'CMD1'])->assertSuccessful();
    $this->artisan('billing:record-payment', ['business' => (string) $this->a->business->uuid, 'amount' => 100])->assertFailed();

    expect(periodEnd($this->a->business)->isFuture())->toBeTrue();
});

it('reopens a business closed for the lapse when it pays before erasure', function () {
    Bus::fake();
    lapsedSubscription($this->a->business);
    app(CloseBusiness::class)->start($this->a->business, null, BusinessClosure::REASON_LAPSED);
    expect($this->a->business->fresh()->status)->toBe(Business::STATUS_CLOSING);

    $this->record->execute($this->a->business, PlatformPayment::SUBSCRIPTION, 3000);

    expect($this->a->business->fresh()->status)->toBe(Business::STATUS_ACTIVE)
        ->and(BusinessClosure::query()->where('business_id', $this->a->business->id)->value('cancelled_at'))->not->toBeNull();
});

describe('through the M-Pesa API', function () {
    beforeEach(function () {
        $this->collector = new FakePaymentCollector;
        app()->instance(PaymentCollector::class, $this->collector);
        lapsedSubscription($this->a->business);
    });

    /**
     * The M-Pesa callback arrives with no signed-in user and no business.
     */
    function callback($test)
    {
        app('auth')->forgetGuards();
        app(TenantContext::class)->forget();

        return $test;
    }

    function startPayment($test, string $phone = '0712345678'): PlatformPayment
    {
        $test->actingAs($test->a->owner)->post(route('billing.pay'), ['phone' => $phone])->assertSessionHas('success');

        return app(TenantContext::class)->run($test->a->business, fn () => PlatformPayment::query()->latest('id')->firstOrFail());
    }

    it('starts an STK push with the payment as the account reference', function () {
        $payment = startPayment($this);

        expect($payment->status)->toBe('pending')
            ->and((float) $payment->amount)->toBe(3000.0)
            ->and($payment->phone)->toBe('254712345678')
            ->and($this->collector->requests[0]['reference'])->toBe((string) $payment->uuid)
            ->and($payment->checkout_request_id)->toBe($this->collector->requests[0]['checkout']);
    });

    it('believes only the API, not the callback', function () {
        $payment = startPayment($this);

        // A callback claiming success; the API says it is still pending.
        callback($this)->postJson('/api/webhooks/platform-payments', ['checkout_request_id' => $payment->checkout_request_id, 'status' => 'paid', 'amount' => 99999])
            ->assertOk();

        expect($payment->fresh()->status)->toBe('pending')
            ->and(periodEnd($this->a->business)->isPast())->toBeTrue();
    });

    it('confirms through the API and applies once, however often the callback repeats', function () {
        $payment = startPayment($this);
        $this->collector->answer($payment->checkout_request_id, new CollectionResult(true, 'QWE123', 3000.0, (string) $payment->uuid, ['ok' => 1]));

        callback($this)->postJson('/api/webhooks/platform-payments', ['CheckoutRequestID' => $payment->checkout_request_id])->assertOk();
        $end = periodEnd($this->a->business);
        callback($this)->postJson('/api/webhooks/platform-payments', ['CheckoutRequestID' => $payment->checkout_request_id])->assertOk();

        expect($payment->fresh()->status)->toBe('confirmed')
            ->and($payment->fresh()->mpesa_receipt)->toBe('QWE123')
            ->and($end->isFuture())->toBeTrue()
            ->and(periodEnd($this->a->business)->equalTo($end))->toBeTrue();
    });

    it('never applies a payment to the wrong business', function () {
        $payment = startPayment($this);
        // The API answers with someone else's account reference.
        $this->collector->answer($payment->checkout_request_id, new CollectionResult(true, 'OTHER1', 3000.0, 'not-our-reference'));

        callback($this)->postJson('/api/webhooks/platform-payments', ['checkout_request_id' => $payment->checkout_request_id])->assertOk();

        expect($payment->fresh()->status)->toBe('failed')
            ->and(periodEnd($this->a->business)->isPast())->toBeTrue();
    });

    it('refuses a paid answer for less than was asked', function () {
        $payment = startPayment($this);
        $this->collector->answer($payment->checkout_request_id, new CollectionResult(true, 'SHORT1', 100.0, (string) $payment->uuid));

        callback($this)->postJson('/api/webhooks/platform-payments', ['checkout_request_id' => $payment->checkout_request_id])->assertOk();

        expect($payment->fresh()->status)->toBe('failed');
    });

    it('lets the owner check a pending payment, and no one else\'s', function () {
        $payment = startPayment($this);
        $this->collector->answer($payment->checkout_request_id, new CollectionResult(true, 'CHK1', 3000.0, (string) $payment->uuid));

        $this->actingAs($this->b->owner)->post(route('billing.payments.check', $payment))->assertNotFound();
        $this->actingAs($this->a->owner)->post(route('billing.payments.check', $payment))->assertSessionHas('success');

        expect($payment->fresh()->status)->toBe('confirmed');
    });

    it('answers a callback for an unknown request the same as any other', function () {
        callback($this)->postJson('/api/webhooks/platform-payments', ['checkout_request_id' => 'nope'])->assertOk()->assertJson(['success' => true]);
        callback($this)->postJson('/api/webhooks/platform-payments', [])->assertOk();
    });
});

it('does not offer online payment before go-live', function () {
    lapsedSubscription($this->a->business);

    $this->actingAs($this->a->owner)->post(route('billing.pay'), ['phone' => '0712345678'])->assertSessionHas('error');
});

it('shows the billing page to owners only, even while read-only', function () {
    lapsedSubscription($this->a->business);

    $this->actingAs($this->a->owner)->get(route('billing.show'))->assertOk()->assertSee('Lapsed')->assertSee('read-only');
    $this->actingAs($this->a->cashier)->get(route('billing.show'))->assertForbidden();
});

it('lowers the shops paid for and warns when that leaves the business over', function () {
    subscribe($this->a->business, 'tier-3', ['shop_count' => 5, 'price_at_signup' => 5000]);

    $this->actingAs($this->a->owner)->post(route('billing.shops'), ['shop_count' => 1])->assertSessionHas('error');

    expect($this->tenant->run($this->a->business, fn () => app(Entitlements::class)->readOnlyReason()))->toBe('shops_over_quota');

    $this->actingAs($this->a->owner)->post(route('billing.shops'), ['shop_count' => 3])->assertSessionHas('success');
    expect($this->tenant->run($this->a->business, fn () => app(Entitlements::class)->readOnlyReason()))->toBeNull();
});
