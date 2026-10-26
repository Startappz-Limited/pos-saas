<?php

namespace App\Services\Sync;

use App\Models\Register;
use App\Models\RegisterLease;
use App\Models\User;
use App\Services\Billing\Entitlements;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Offline trading leases (Phase 7, decision 4.5, the proposed answer
 * adopted in the stage file). A device clock can be changed, so the server
 * says how long a till may trade offline:
 *
 * - every sync issues a lease: `trade_until` is now plus
 *   `config('sync.lease_days')`, never past the subscription's paid-up date
 *   (trial end or period end); a lapsed subscription gets no time beyond now;
 *   a business with no subscription is unmetered and gets the full days;
 * - the lease is recorded and signed (HMAC with the app key), so the till can
 *   show it and send it back, and the server can tell it is genuine;
 * - a pushed sale is accepted only if its recorded time lies inside a lease
 *   the server issued to that register (covers()).
 */
class TradingLease
{
    public function __construct(private Entitlements $entitlements) {}

    /**
     * Issues and records a new lease for $register in the current business.
     */
    public function issue(Register $register, ?User $user = null): RegisterLease
    {
        $now = Carbon::now();

        return RegisterLease::create([
            'shop_id' => $register->shop_id,
            'register_id' => $register->id,
            'issued_to' => $user?->id,
            'issued_at' => $now,
            'trade_until' => $this->tradeUntil($now),
        ]);
    }

    /**
     * The lease as the till receives it.
     *
     * @return array{uuid: string, register: string, issued_at: string, trade_until: string, signature: string}
     */
    public function present(RegisterLease $lease, Register $register): array
    {
        $payload = [
            'uuid' => $lease->uuid,
            'register' => $register->uuid,
            'issued_at' => $lease->issued_at->toIso8601String(),
            'trade_until' => $lease->trade_until->toIso8601String(),
        ];

        return [...$payload, 'signature' => self::sign($payload)];
    }

    /**
     * Whether a lease issued to $registerId covers $recordedAt: issued at or
     * before it, and trading allowed until at or after it.
     */
    public function covers(int $registerId, CarbonInterface $recordedAt): bool
    {
        return RegisterLease::query()
            ->where('register_id', $registerId)
            ->where('issued_at', '<=', $recordedAt)
            ->where('trade_until', '>=', $recordedAt)
            ->exists();
    }

    /**
     * Whether a lease the till sent back is one the server signed.
     *
     * @param  array<string, mixed>  $lease
     */
    public static function isGenuine(array $lease): bool
    {
        $payload = array_intersect_key($lease, array_flip(['uuid', 'register', 'issued_at', 'trade_until']));

        return count($payload) === 4
            && is_string($lease['signature'] ?? null)
            && hash_equals(self::sign($payload), $lease['signature']);
    }

    /**
     * @param  array{uuid: string, register: string, issued_at: string, trade_until: string}  $payload
     */
    private static function sign(array $payload): string
    {
        $message = implode('|', [$payload['uuid'], $payload['register'], $payload['issued_at'], $payload['trade_until']]);

        return hash_hmac('sha256', 'register-lease|'.$message, (string) config('app.key'));
    }

    private function tradeUntil(Carbon $now): Carbon
    {
        $until = $now->copy()->addDays(max(0, (int) config('sync.lease_days')));
        $subscription = $this->entitlements->subscription();

        if ($subscription === null) {
            return $until;
        }

        if (! $subscription->isInGoodStanding()) {
            return $now->copy();
        }

        $paidUpTo = $subscription->endsAt();

        return $paidUpTo !== null && $paidUpTo->lessThan($until) ? Carbon::instance($paidUpTo) : $until;
    }
}
