<?php

namespace App\Jobs;

use App\Actions\Baileys\NotifyCustomerOfCreditStatement;
use App\Models\CreditAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Deliver a customer's outstanding-debt statement over Baileys (Chatway Gateway).
 *
 * Queued rather than inline because rendering the PDF and uploading it takes
 * hundreds of milliseconds, which would otherwise be paid by whoever tapped the
 * button. Runs on the `baileys` queue — a worker that does not name that queue
 * will never send these.
 */
class SendCreditStatementViaBaileysJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public CreditAccount $creditAccount,
        public ?int $sentBy = null,
    ) {
        $this->onQueue(config('baileys.queue', 'baileys'));
    }

    public function handle(NotifyCustomerOfCreditStatement $notify): void
    {
        $message = $notify->execute($this->creditAccount, $this->sentBy);

        if ($message === null) {
            Log::info('Credit statement not sent: preconditions not met', [
                'credit_account_id' => $this->creditAccount->id,
                'reason' => 'no connected Baileys session, or customer has no usable phone number',
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendCreditStatementViaBaileysJob failed', [
            'credit_account_id' => $this->creditAccount->id,
            'error' => $e->getMessage(),
        ]);
    }
}
