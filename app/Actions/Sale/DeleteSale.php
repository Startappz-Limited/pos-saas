<?php

namespace App\Actions\Sale;

use App\Actions\SettleCreditSalePayment;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Sale;
use App\Models\User;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a sale (stage 10, step 5): only a draft or pending sale with no
 * payments, such as a cash-on-delivery sale never paid. A completed sale is
 * voided instead, and so is a pending sale with payments (its money is handed
 * back as a void does). Stock is taken at creation for every status, so the
 * lines are released (`SALE_DELETE`) first. The sale is soft-deleted: its
 * row, lines and invoice number stay, the number is never issued again, and
 * it leaves every list and report. Who may delete is SalePolicy::delete.
 */
class DeleteSale
{
    public function __construct(private StockLedger $ledger, private SettleCreditSalePayment $credit) {}

    /**
     * @throws BusinessRuleException with a code: `sale_completed`, `sale_not_deletable` or `sale_has_payments`
     */
    public function execute(Sale $sale, User $by): int
    {
        return DB::transaction(function () use ($sale, $by): int {
            $sale = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->status === 'completed') {
                throw BusinessRuleException::because(__('Completed sales can\'t be deleted. Void the sale instead.'), 'sale_completed');
            }
            if (! in_array($sale->status, ['draft', 'pending'], true)) {
                throw BusinessRuleException::because(__('Only draft and pending sales can be deleted.'), 'sale_not_deletable');
            }
            if ((float) $sale->paid_amount > 0 || $sale->payments()->exists()) {
                throw BusinessRuleException::because(__('This sale has payments. Void it instead.'), 'sale_has_payments');
            }

            $units = 0;
            foreach ($sale->items()->orderBy('id')->get() as $item) {
                $units += $this->ledger->release($item, StockMovementType::SALE_DELETE, ['reference' => $sale, 'user_id' => $by->id, 'notes' => "Sale {$sale->invoice_number} deleted."])?->quantity ?? 0;
            }
            $this->credit->reverse($sale, __('Sale deleted'));
            $sale->delete();

            return $units;
        });
    }
}
