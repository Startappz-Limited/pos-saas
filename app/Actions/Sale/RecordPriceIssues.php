<?php

namespace App\Actions\Sale;

use App\Models\Register;
use App\Models\Sale;
use App\Models\SyncIssue;
use App\Support\Sync\SyncIssues;

/**
 * Puts a till's push that charged below the expected price, or discounted beyond the seller's limit, on the sync review
 * (stage 11b, step 5). Never refused: the sale is recorded as charged, and each such line or discount is one
 * `price_mismatch` issue. It is open, unless the approver the till named held `sales.override-price` on the server when
 * the sale arrived, in which case it is recorded already resolved as an authorised override.
 */
final class RecordPriceIssues
{
    public function __construct(private readonly SyncIssues $issues) {}

    public function execute(Sale $sale, PriceAssessment $assessment, ?Register $register): void
    {
        $about = ['sale' => $sale->uuid, 'invoice_number' => $sale->invoice_number, 'till' => $register?->id];

        foreach ($assessment->shortLines() as $line) {
            $issue = $this->issues->record(SyncIssue::PRICE_MISMATCH, "price:{$sale->uuid}:{$line['index']}", [
                ...$about,
                'kind' => 'line',
                'line' => $line['index'],
                'product_id' => $line['product_id'],
                'expected_unit_price' => $line['expected_unit_price'],
                'charged_unit_price' => $line['charged_unit_price'],
                'expected_total' => $line['expected_total'],
                'charged_total' => $line['charged_total'],
                'person' => $line['approver']?->name,
                'held' => $line['approved'],
            ], $register);

            $this->resolveIfApproved($issue, $line['approved'], 'authorised override');
        }

        $discount = $assessment->discount;
        if (! $discount['within'] && (bccomp($discount['amount'], '0', 2) > 0)) {
            $issue = $this->issues->record(SyncIssue::PRICE_MISMATCH, "discount:{$sale->uuid}", [
                ...$about,
                'kind' => 'discount',
                'discount' => $discount['amount'],
                'limit' => $discount['limit'],
                'person' => $discount['approver']?->name,
                'held' => $discount['approved'],
            ], $register);

            $this->resolveIfApproved($issue, $discount['approved'], 'authorised discount');
        }
    }

    private function resolveIfApproved(SyncIssue $issue, bool $approved, string $resolution): void
    {
        if ($approved && $issue->resolved_at === null) {
            $issue->update(['resolved_at' => now(), 'resolution' => $resolution]);
        }
    }
}
