<?php

namespace App\Support\Receipts;

use App\Contracts\FiscalProvider;
use App\Enums\TaxClass;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;

/**
 * A sale's receipt as structured data for an ESC/POS printer (Phase 7): the
 * till lays it out for its paper width; the server decides what is on it.
 * Money is sent as strings with two decimals, times in the shop's timezone.
 *
 * Sections: `header` (business and shop), `sale` (numbers, time, till,
 * cashier, customer), `lines`, `totals`, `tax` (per class, for a VAT
 * registered shop), `payments`, `footer`, and `fiscal` (the eTIMS block, null
 * until fiscal signing is connected).
 */
class SaleReceipt
{
    public function __construct(private FiscalProvider $fiscal) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Sale $sale): array
    {
        $sale->loadMissing(['shop.business', 'items.product', 'items.variation', 'payments', 'createdBy:id,name', 'customer:id,name,phone,tax_pin', 'register.register:id,code,name']);
        $shop = $sale->shop;
        $business = $shop?->business;
        $timezone = $shop?->timezone ?: ($business?->timezone ?: config('app.timezone'));
        $money = fn ($value): string => number_format((float) $value, 2, '.', '');

        return [
            'header' => [
                'business' => $business?->name,
                'shop' => $shop?->name,
                'address' => $shop?->address,
                'phone' => $shop?->phone,
                'tax_pin' => $shop?->tax_pin,
                'vat_registered' => (bool) $shop?->vat_registered,
                'text' => $business?->receipt_header,
            ],
            'sale' => [
                'uuid' => $sale->uuid,
                'invoice_number' => $sale->invoice_number,
                'status' => $sale->status,
                'date' => ($sale->completed_at ?? $sale->created_at)?->setTimezone($timezone)->format('Y-m-d H:i'),
                'till' => $sale->register?->register?->code,
                'cashier' => $sale->createdBy?->name,
                'customer' => $sale->customer?->name ?? $sale->walk_in_customer_name,
                'customer_tax_pin' => $sale->customer_tax_pin,
                'tax_inclusive' => (bool) $sale->tax_inclusive,
            ],
            'lines' => $sale->items->map(fn (SaleItem $item): array => [
                'name' => $item->product?->name,
                'variation' => $item->variation && ! $item->variation->is_default ? $item->variation->name : null,
                'quantity' => (int) $item->quantity,
                'unit_price' => $money($item->unit_price),
                'total' => $money($item->line_total),
                'tax_code' => TaxClass::tryFrom((string) $item->tax_class)?->kraCode(),
                'tax_rate' => $item->tax_rate === null ? null : $money($item->tax_rate),
            ])->values()->all(),
            'totals' => [
                'subtotal' => $money($sale->subtotal),
                'discount' => $money($sale->discount_amount),
                'delivery_fee' => $money($sale->delivery_fee),
                'packaging_fee' => $money($sale->packaging_fee),
                'other_charges' => $money($sale->other_expenses),
                'tax' => $money($sale->tax_amount),
                'total' => $money($sale->total_amount),
                'paid' => $money($sale->paid_amount),
                'balance_due' => $money($sale->balance_due),
            ],
            'tax' => collect($sale->tax_breakdown ?? [])->map(fn ($band, $key): array => [
                'class' => is_array($band) ? ($band['class'] ?? $band['tax_class'] ?? (string) $key) : (string) $key,
                'rate' => is_array($band) && isset($band['rate']) ? $money($band['rate']) : null,
                'taxable' => is_array($band) && isset($band['taxable']) ? $money($band['taxable']) : null,
                'tax' => is_array($band) && isset($band['tax']) ? $money($band['tax']) : null,
            ])->values()->all(),
            'payments' => $sale->payments->map(fn (SalePayment $payment): array => [
                'method' => $payment->payment_method,
                'amount' => $money($payment->amount),
                'reference' => $payment->reference,
            ])->values()->all(),
            'footer' => $business?->receipt_footer,
            'fiscal' => $this->fiscal->signatureFor($sale)?->toArray(),
        ];
    }
}
