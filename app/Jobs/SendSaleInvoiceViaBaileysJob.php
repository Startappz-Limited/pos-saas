<?php

namespace App\Jobs;

use App\Actions\Baileys\NotifyCustomerOfSale;
use App\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Deliver a completed sale's invoice PDF to the customer over Baileys (Chatway Gateway).
 *
 * Queued rather than inline because rendering the PDF and uploading it to the
 * gateway takes hundreds of milliseconds — latency that would otherwise be paid
 * by the cashier at the till on every sale.
 */
class SendSaleInvoiceViaBaileysJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public Sale $sale)
    {
        $this->onQueue(config('baileys.queue', 'baileys'));
    }

    public function handle(NotifyCustomerOfSale $notify): void
    {
        $message = $notify->execute($this->sale);

        if ($message === null) {
            Log::debug('Baileys sale invoice not sent: preconditions not met', [
                'sale_id' => $this->sale->id,
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendSaleInvoiceViaBaileysJob failed', [
            'sale_id' => $this->sale->id,
            'error' => $e->getMessage(),
        ]);
    }
}
