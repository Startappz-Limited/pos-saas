<?php

use App\Enums\ApiErrorCode;
use App\Models\Register;
use App\Models\Subscription;
use App\Services\Sync\TradingLease;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\Till;
use Tests\Support\TwoBusinesses;

/**
 * Offline trading leases, Phase 7 (step 3): pairing and every sync give the
 * till a signed "trade until" time, never past the subscription's paid-up
 * date; the server records each lease and accepts a recorded time only inside
 * one it issued to that register.
 */
beforeEach(function () {
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->tenant = app(TenantContext::class);
    $this->a = $this->fixture->a;
    [$this->register, $this->other] = $this->tenant->run($this->a->business, fn (): array => array_map(
        fn ($shop) => Register::defaultFor($shop->id),
        array_values($this->a->shops),
    ));
    $this->tenant->forget();
    config(['sync.lease_days' => 3]);
});

/**
 * GET /api/v1/sync/lease with $token.
 */
function leaseWith(string $token)
{
    Till::freshRequest();

    return test()->withToken($token)->getJson('/api/v1/sync/lease');
}

it('gives a lease with pairing and on request, trading until now plus the configured days', function () {
    $this->freezeTime();

    $paired = Till::pair(Till::signIn($this->a->owner), $this->register)->assertOk();
    expect($paired->json('meta.lease.trade_until'))->toBe(now()->addDays(3)->toIso8601String())
        ->and($paired->json('meta.lease.register'))->toBe($this->register->uuid);

    $lease = leaseWith($paired->json('data.token'))->assertOk()
        ->assertJsonPath('meta.lease.trade_until', now()->addDays(3)->toIso8601String())
        ->json('meta.lease');

    expect(TradingLease::isGenuine($lease))->toBeTrue()
        ->and(DB::table('register_leases')->where('register_id', $this->register->id)->count())->toBe(2);
});

it('never leases past the subscription\'s paid-up date', function () {
    $this->freezeTime();
    subscribe($this->a->business, 'tier-2', ['status' => Subscription::TRIALING, 'trial_ends_at' => now()->addDay(), 'current_period_end' => null]);

    $token = Till::token($this->a->owner, $this->register);

    leaseWith($token)->assertOk()->assertJsonPath('meta.lease.trade_until', now()->addDay()->toIso8601String());
});

it('gives a lapsed business no offline time beyond now', function () {
    $token = Till::token($this->a->owner, $this->register);
    $this->freezeTime();
    subscribe($this->a->business, 'tier-2', ['current_period_end' => now()->subDay()]);

    leaseWith($token)->assertOk()->assertJsonPath('meta.lease.trade_until', now()->toIso8601String());
});

it('refuses a token that is not bound to a till', function () {
    leaseWith(Till::signIn($this->a->owner))->assertForbidden()
        ->assertJsonPath('code', ApiErrorCode::RegisterTokenRequired->value);
});

it('tells a genuine lease from an altered one', function () {
    $lease = Till::pair(Till::signIn($this->a->owner), $this->register)->json('meta.lease');

    expect(TradingLease::isGenuine($lease))->toBeTrue()
        ->and(TradingLease::isGenuine([...$lease, 'trade_until' => Carbon::parse($lease['trade_until'])->addYear()->toIso8601String()]))->toBeFalse()
        ->and(TradingLease::isGenuine([...$lease, 'register' => $this->other->uuid]))->toBeFalse()
        ->and(TradingLease::isGenuine(array_diff_key($lease, ['signature' => true])))->toBeFalse();
});

it('covers only times inside a lease issued to that register', function () {
    $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));
    Till::token($this->a->owner, $this->register);
    $leases = app(TradingLease::class);

    $covers = fn (Register $register, string $at): bool => $this->tenant->run($this->a->business, fn () => $leases->covers($register->id, Carbon::parse($at)));

    expect($covers($this->register, '2026-10-26 09:00:00'))->toBeTrue()
        ->and($covers($this->register, '2026-10-29 08:59:00'))->toBeTrue()
        ->and($covers($this->register, '2026-10-29 09:01:00'))->toBeFalse()
        ->and($covers($this->register, '2026-10-26 08:59:00'))->toBeFalse()
        ->and($covers($this->other, '2026-10-27 12:00:00'))->toBeFalse();
});
