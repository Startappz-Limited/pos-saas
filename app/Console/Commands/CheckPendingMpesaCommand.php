<?php

namespace App\Console\Commands;

use App\Models\PaymentRequest;
use App\Services\Mpesa\TillPayments;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Asks M-Pesa about every pending till payment (Phase 7): an STK Push each
 * run until it settles or expires, an entered code every 15 minutes until it
 * is found or given up. Confirmed payments are recorded against their sales.
 * Run every minute by the scheduler, so a till that went offline after an STK
 * Push still gets its sale paid.
 */
class CheckPendingMpesaCommand extends Command
{
    protected $signature = 'mpesa:check-pending';

    protected $description = 'Confirm pending M-Pesa payments made at tills';

    public function handle(TenantContext $tenant, TillPayments $payments): int
    {
        $checked = 0;

        $tenant->eachBusiness(function () use ($payments, &$checked): void {
            PaymentRequest::query()->where('status', PaymentRequest::PENDING)
                ->where(fn ($query) => $query->where('method', PaymentRequest::METHOD_STK)
                    ->orWhereNull('last_checked_at')
                    ->orWhere('last_checked_at', '<', now()->subMinutes(15)))
                ->orderBy('id')->limit(500)->get()
                ->each(function (PaymentRequest $request) use ($payments, &$checked): void {
                    $payments->check($request);
                    $checked++;
                });
        });

        $this->info("Checked {$checked} pending M-Pesa payment(s).");

        return self::SUCCESS;
    }
}
