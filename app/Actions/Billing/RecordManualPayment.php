<?php

namespace App\Actions\Billing;

use App\Models\Business;
use App\Models\PlatformPayment;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Records money the platform received outside the automated path (Phase 4,
 * decision 4.6: until M-Pesa go-live payments are recorded by hand, by the
 * platform operator) and applies it. An M-Pesa receipt number, when given, is
 * unique: the same receipt can't be recorded twice.
 */
class RecordManualPayment
{
    public function __construct(private TenantContext $tenant, private ApplyPlatformPayment $apply) {}

    public function execute(Business $business, string $purpose, float $amount, ?string $receipt = null, ?User $recordedBy = null, ?string $notes = null): PlatformPayment
    {
        // Recorded and applied together: a payment that cannot be applied
        // (too small for a month, below the top-up minimum) leaves no record.
        return DB::transaction(function () use ($business, $purpose, $amount, $receipt, $recordedBy, $notes): PlatformPayment {
            $payment = $this->tenant->run($business, fn (): PlatformPayment => PlatformPayment::create([
                'purpose' => $purpose,
                'amount' => $amount,
                'currency' => config('billing.currency', 'KES'),
                'method' => PlatformPayment::MANUAL,
                'status' => PlatformPayment::CONFIRMED,
                'mpesa_receipt' => $receipt,
                'recorded_by' => $recordedBy?->id,
                'notes' => $notes,
                'confirmed_at' => now(),
            ]));

            $this->apply->execute($payment);

            return $this->tenant->run($business, fn (): PlatformPayment => $payment->fresh());
        });
    }
}
