<?php

use App\Actions\Business\StartTrial;
use App\Models\AiCreditBalance;
use App\Models\AuditLog;
use App\Models\PlatformPayment;
use App\Models\Subscription;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Tests\Support\TwoBusinesses;

/**
 * Manual payments and AI top-ups in the platform console, Phase 5 (B2;
 * decision 4.6, until M-Pesa go-live): recorded and applied together,
 * attributed to the operator who entered them, audited; a receipt is
 * recorded once on the whole platform; a payment that can't be applied
 * leaves no record. (Owners get a 404: PlatformAccessTest.)
 */
beforeEach(function () {
    migrateAuditDatabase();
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->operator = platformOperator();
    $this->tenant = app(TenantContext::class);
    $this->tenant->forget();
    $this->b = $this->fixture->b->business;
    app(StartTrial::class)->execute($this->b);
});

/**
 * @return Collection<int, PlatformPayment>
 */
function paymentsOf($business)
{
    return app(TenantContext::class)->run($business, fn () => PlatformPayment::query()->get());
}

it('records a subscription payment in the operator\'s name and applies it', function () {
    $monthly = $this->tenant->run($this->b, fn () => Subscription::query()->first()->monthlyTotal());

    $this->actingAs($this->operator)->post(route('platform.businesses.payments.store', $this->b), [
        'purpose' => 'subscription', 'amount' => $monthly * 2, 'receipt' => 'QWE123ABC', 'notes' => 'Paid at the office',
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    $payment = paymentsOf($this->b)->sole();
    $subscription = $this->tenant->run($this->b, fn () => Subscription::query()->first());

    expect($payment->recorded_by)->toBe($this->operator->id)
        ->and($payment->method)->toBe(PlatformPayment::MANUAL)
        ->and($payment->mpesa_receipt)->toBe('QWE123ABC')
        ->and($payment->isApplied())->toBeTrue()
        ->and($subscription->status)->toBe(Subscription::ACTIVE)
        ->and($subscription->current_period_end->isAfter(now()->addMonth()->addDays(25)))->toBeTrue()
        ->and(paymentsOf($this->fixture->a->business))->toHaveCount(0);

    $audit = AuditLog::query()->latest('id')->get()->first(fn ($row) => in_array('payment.recorded', $row->tags ?? [], true));
    expect($audit->business_id)->toBe($this->b->id)
        ->and($audit->user_id)->toBe($this->operator->id)
        ->and($audit->new_values['payment'])->toBe($payment->uuid);

    // The business page lists it with who recorded it.
    $this->actingAs($this->operator)->get(route('platform.businesses.show', $this->b))->assertOk()
        ->assertSeeText('QWE123ABC')->assertSeeText($this->operator->name);
});

it('records an AI top-up and credits that business only', function () {
    $this->actingAs($this->operator)->post(route('platform.businesses.payments.store', $this->b), ['purpose' => 'ai_topup', 'amount' => 1000])
        ->assertSessionHasNoErrors();

    expect((float) $this->tenant->run($this->b, fn () => AiCreditBalance::query()->value('balance')))->toBeGreaterThan(0.0)
        ->and($this->tenant->run($this->fixture->a->business, fn () => AiCreditBalance::query()->value('balance')))->toBeNull()
        ->and(paymentsOf($this->b)->sole()->recorded_by)->toBe($this->operator->id);
});

it('records a receipt once on the whole platform', function () {
    $this->actingAs($this->operator)->post(route('platform.businesses.payments.store', $this->b), ['purpose' => 'ai_topup', 'amount' => 600, 'receipt' => 'RCPT777'])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->operator)->post(route('platform.businesses.payments.store', $this->fixture->a->business), ['purpose' => 'ai_topup', 'amount' => 600, 'receipt' => 'RCPT777'])
        ->assertSessionHasErrors(['receipt' => 'This M-Pesa receipt has already been recorded.']);

    expect(paymentsOf($this->fixture->a->business))->toHaveCount(0);
});

it('refuses a top-up under the minimum and a payment less than a month, leaving no record', function () {
    $this->actingAs($this->operator)->post(route('platform.businesses.payments.store', $this->b), ['purpose' => 'ai_topup', 'amount' => 100])
        ->assertSessionHasErrors(['amount' => 'The minimum AI top-up is KES 500.']);

    $this->actingAs($this->operator)->post(route('platform.businesses.payments.store', $this->b), ['purpose' => 'subscription', 'amount' => 10])
        ->assertSessionHasErrors('amount');

    $this->actingAs($this->operator)->post(route('platform.businesses.payments.store', $this->b), ['purpose' => 'refund', 'amount' => 600])
        ->assertSessionHasErrors('purpose');

    expect(paymentsOf($this->b))->toHaveCount(0);
});
