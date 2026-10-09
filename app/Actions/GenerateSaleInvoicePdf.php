<?php

namespace App\Actions;

use App\Actions\Baileys\NotifyCustomerOfSale;
use App\Http\Controllers\SaleController;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Render a sale's invoice as a PDF.
 *
 * The same rendering path backs both the signed download route
 * ({@see SaleController::invoicePdf()}) and the WhatsApp
 * document delivery ({@see NotifyCustomerOfSale}), so the
 * customer's WhatsApp attachment and their download link are always identical.
 */
class GenerateSaleInvoicePdf
{
    /**
     * Render the invoice and return the raw PDF bytes.
     */
    public function execute(Sale $sale): string
    {
        $sale->loadMissing(['shop', 'customer', 'items.product', 'items.variation', 'deliveryCompany', 'payments']);

        return Pdf::loadView('pdf.sale-invoice', [
            'sale' => $sale,
            'shop' => $sale->shop,
        ])->output();
    }

    /**
     * Filename shown to the customer when they receive or download the invoice.
     */
    public function filename(Sale $sale): string
    {
        $slug = Str::slug($sale->invoice_number ?: (string) $sale->uuid);

        return "invoice-{$slug}.pdf";
    }

    /**
     * Permanent signed link the customer can reopen the invoice from.
     *
     * Mirrors the ecommerce order invoice: no expiry, but unguessable and
     * tamper-proof, so it is safe to hand to a customer over WhatsApp.
     */
    public function signedUrl(Sale $sale): string
    {
        return URL::signedRoute('sales.invoice-pdf', $sale);
    }
}
