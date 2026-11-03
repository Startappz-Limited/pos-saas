<?php

namespace App\Actions\Sale;

use App\Actions\SettleCreditSalePayment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Voids a sale: what the web and flat API void did inline, shared with an
 * approved void request (stage 10, step 2). Marks it voided, releases the debt
 * a credit sale created, and recalculates its session's totals. Who may void,
 * and until when, is the caller's check (SalePolicy::void, or the request's
 * own window). Stock and cash on a void are step 4.
 */
class VoidSale
{
    public function __construct(private SettleCreditSalePayment $credit) {}

    public function execute(Sale $sale, string $reason, User $by): Sale
    {
        DB::transaction(function () use ($sale, $reason, $by): void {
            $sale->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => $by->id,
                'void_reason' => $reason,
            ]);

            // Release the debt a voided credit sale created, or the customer
            // keeps owing for a sale that no longer exists.
            $this->credit->reverse($sale, $reason);
        });

        if ($sale->register_id) {
            $sale->register?->updateSalesTotals();
        }

        return $sale;
    }
}
