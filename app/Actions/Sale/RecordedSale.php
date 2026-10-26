<?php

namespace App\Actions\Sale;

use App\Models\Sale;

/**
 * What RecordSale made: the sale, and whether it put a credit customer over
 * their limit (the sale is still made; the till shows a warning).
 */
final class RecordedSale
{
    public function __construct(
        public readonly Sale $sale,
        public readonly bool $overCreditLimit,
    ) {}
}
