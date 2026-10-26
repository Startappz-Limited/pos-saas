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
    ) {}
}
