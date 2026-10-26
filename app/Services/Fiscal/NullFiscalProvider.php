<?php

namespace App\Services\Fiscal;

use App\Contracts\FiscalProvider;
use App\Models\Sale;

/**
 * Bound until KRA eTIMS is connected (open question 6): no sale is signed,
 * so receipts carry no fiscal block.
 */
class NullFiscalProvider implements FiscalProvider
{
    public function signatureFor(Sale $sale): ?FiscalSignature
    {
        return null;
    }
}
