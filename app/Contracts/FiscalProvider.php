<?php

namespace App\Contracts;

use App\Models\Sale;
use App\Services\Fiscal\FiscalSignature;

/**
 * The seam for fiscal signing (Phase 7; open question 6, eTIMS deferred): a
 * provider takes a completed sale to the tax authority and returns what must
 * be printed on its receipt. Until KRA eTIMS is connected the null provider
 * is bound and receipts carry no fiscal block. TaxClass::kraCode() is what an
 * eTIMS provider will send for each line.
 */
interface FiscalProvider
{
    /**
     * The fiscal signature for $sale, or null when the sale isn't (or can't
     * yet be) signed.
     */
    public function signatureFor(Sale $sale): ?FiscalSignature;
}
