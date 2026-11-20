<?php

namespace App\Services\Stock;

use App\Models\StockMovement;

/**
 * The outcome of one StockLedger change.
 */
final class StockChange
{
    /**
     * @param  string  $quantity  base units moved, as a scale-3 decimal string (stage 11, step 1)
     * @param  float  $totalCost  cost of the units, unknown costs counted as zero
     * @param  bool  $costKnown  false when any unit's cost is unknown
     */
    public function __construct(
        public readonly StockMovement $movement,
        public readonly string $quantity,
        public readonly float $totalCost,
        public readonly bool $costKnown,
    ) {}

    /**
     * The weighted unit cost, unknown costs counted as zero.
     */
    public function unitCost(): float
    {
        return bccomp($this->quantity, '0', 3) > 0 ? round($this->totalCost / (float) $this->quantity, 2) : 0.0;
    }
}
