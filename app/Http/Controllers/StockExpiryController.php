<?php

namespace App\Http\Controllers;

use App\Actions\Stock\WriteOffExpiredStock;
use App\Http\Requests\WriteOffExpiredStockRequest;
use App\Models\CostLayer;
use App\Models\Shop;
use App\Support\Stock\Expiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Expiring stock (stage 11, step 5): per shop, the batches already expired and those expiring
 * within N days (30 by default), and the "Write off expired" action, which files a stock
 * adjustment for a second person to approve.
 */
class StockExpiryController extends Controller
{
    public function __construct(private readonly WriteOffExpiredStock $writeOff) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('stock.view'), 403);

        $shops = Shop::active()->select('id', 'name', 'timezone', 'business_id')->orderBy('name')->get();
        $shop = $shops->firstWhere('id', (int) $request->input('shop_id')) ?? $shops->first();
        $days = max(1, min(365, (int) $request->input('days', 30)));

        $expired = collect();
        $expiring = collect();

        if ($shop !== null) {
            $today = Expiry::firstSellableDate($shop);
            $layers = CostLayer::query()
                ->with(['variation:id,product_id,name,is_default', 'product:id,name'])
                ->where('shop_id', $shop->id)
                ->where('remaining_quantity', '>', 0)
                ->whereNotNull('expiry_date')
                ->where('expiry_date', '<=', $today->addDays($days)->toDateString())
                ->orderBy('expiry_date')->orderBy('id')
                ->get();

            [$expired, $expiring] = $layers->partition(fn (CostLayer $layer): bool => $layer->expiry_date->lt($today));
        }

        return view('stock-expiry.index', [
            'shops' => $shops,
            'shop' => $shop,
            'days' => $days,
            'expired' => $expired,
            'expiring' => $expiring,
            'fractionsLeft' => $shop !== null ? $this->writeOff->fractionsLeft($shop) : collect(),
            'showCosts' => $request->user()->can('products.view-cost') || $request->user()->can('products.set-cost'),
        ]);
    }

    public function writeOff(WriteOffExpiredStockRequest $request): RedirectResponse
    {
        $shop = Shop::findOrFail($request->validated('shop_id'));

        try {
            $adjustment = $this->writeOff->execute($shop, $request->user()->id);
        } catch (\Exception $e) {
            return $this->failed($e);
        }

        return redirect()->route('stock-adjustments.show', $adjustment)->with('success', $adjustment->isCompleted()
            ? __('Expired stock written off in adjustment :number.', ['number' => $adjustment->adjustment_number])
            : __('Adjustment :number saved. It waits for someone else to approve the write-off.', ['number' => $adjustment->adjustment_number]));
    }
}
