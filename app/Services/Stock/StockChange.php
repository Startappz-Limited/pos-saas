<?php

namespace App\Services\Stock;

use App\Models\StockMovement;

/**
 * The outcome of one StockLedger change.
 */
final class StockChange
{
    /**
     * @param  float  $totalCost  cost of the units, unknown costs counted as zero
     * @param  bool  $costKnown  false when any unit's cost is unknown
     */
    public function __construct(
        public readonly StockMovement $movement,
        public readonly int $quantity,
        public readonly float $totalCost,
        public readonly bool $costKnown,
    ) {}

    /**
     * The weighted unit cost, unknown costs counted as zero.
     */
    public function unitCost(): float
    {
        return $this->quantity > 0 ? round($this->totalCost / $this->quantity, 2) : 0.0;
    }
}
