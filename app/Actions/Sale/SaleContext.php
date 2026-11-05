<?php

namespace App\Actions\Sale;

use App\Models\CashRegister;
use Carbon\CarbonInterface;

/**
 * How a sale a till recorded offline differs from one made now (Phase 7):
 * its own time, its till and cash session, who sold it, its client uuid and
 * the invoice number the till gave it. All optional; none means "now, as the
 * flat API does".
 */
final class SaleContext
{
    public function __construct(
        public readonly ?CarbonInterface $recordedAt = null,
        public readonly ?string $clientUuid = null,
        public readonly ?int $shopId = null,
        public readonly ?int $registerId = null,
        public readonly ?CashRegister $cashSession = null,
        public readonly ?string $invoiceNumber = null,
        public readonly ?int $soldBy = null,
        // Paid by M-Pesa at the till, confirmed later: recorded unpaid until then.
        public readonly bool $awaitingPayment = false,
        // A held till sale accepted with an unusual number (stage 10, step 5c): kept as the till gave it.
        public readonly bool $invoiceAsGiven = false,
        // Number it in the shop's web series (stage 10, steps 5c and 5d): a re-rung till sale.
        public readonly bool $webSeries = false,
    ) {}
}
