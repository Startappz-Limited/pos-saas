<?php

namespace App\Console\Commands;

use App\Actions\Billing\RecordManualPayment;
use App\Exceptions\PaymentRejected;
use App\Models\Business;
use App\Models\PlatformPayment;
use Illuminate\Console\Command;

/**
 * Records a payment the platform received by hand (Phase 4, decision 4.6:
 * until M-Pesa go-live) and applies it. The platform console (Phase 5) will
 * offer the same.
 */
class RecordPlatformPayment extends Command
{
    protected $signature = 'billing:record-payment
        {business : The business id or uuid}
        {amount : Amount received, in KES}
        {--purpose=subscription : subscription or ai_topup}
        {--receipt= : The M-Pesa receipt number}
        {--notes= : A note}';

    protected $description = 'Record a payment received by hand and apply it to a business';

    public function handle(RecordManualPayment $record): int
    {
        $key = (string) $this->argument('business');
        $business = Business::query()->where('uuid', $key)->orWhere('id', ctype_digit($key) ? (int) $key : 0)->first();

        if ($business === null) {
            $this->error('No such business.');

            return self::FAILURE;
        }

        $purpose = (string) $this->option('purpose');
        if (! in_array($purpose, [PlatformPayment::SUBSCRIPTION, PlatformPayment::AI_TOPUP], true)) {
            $this->error('The purpose must be subscription or ai_topup.');

            return self::FAILURE;
        }

        try {
            $payment = $record->execute($business, $purpose, (float) $this->argument('amount'), $this->option('receipt') ?: null, null, $this->option('notes') ?: null);
        } catch (PaymentRejected $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Recorded and applied payment {$payment->uuid} for {$business->name}.");

        return self::SUCCESS;
    }
}
