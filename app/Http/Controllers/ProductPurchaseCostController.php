<?php

namespace App\Http\Controllers;

use App\Actions\Product\SetProductPurchaseCostsAction;
use App\Http\Requests\UpdateProductPurchaseCostRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\PurchaseCostService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manual entry of purchase costs for products that did not arrive through
 * inventory intake.
 *
 * Products created by the WooCommerce/Shopify importers have no supplier
 * invoice behind them, so their cost is deliberately left unset rather than
 * guessed from a selling price. This screen is where a user holding
 * `products.set-cost` supplies the real figure.
 */
class ProductPurchaseCostController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private PurchaseCostService $purchaseCostService) {}

    public function index(Request $request): View
    {
        $this->authorize('setCost', Product::class);

        $shopId = $request->integer('shop_id') ?: null;

        $products = $this->purchaseCostService->needingAttention(
            filter: $request->get('filter', 'all'),
            search: $request->get('search'),
            categoryId: $request->integer('category_id') ?: null,
            shopId: $shopId,
            perPage: $request->integer('per_page') ?: 25,
        );

        $suggestions = $this->purchaseCostService->suggestedCosts(
            $products->pluck('id')->all()
        );

        return view('products.purchase-costs', [
            'products' => $products,
            'suggestions' => $suggestions,
            'statistics' => $this->purchaseCostService->statistics($shopId),
            'categories' => Category::active()->ordered()->get(),
            'filter' => $request->get('filter', 'all'),
        ]);
    }

    public function update(
        UpdateProductPurchaseCostRequest $request,
        SetProductPurchaseCostsAction $setPurchaseCosts,
    ): RedirectResponse {
        $this->authorize('setCost', Product::class);

        $result = $setPurchaseCosts->execute(
            $request->productCosts(),
            $request->variationCosts(),
        );

        $updated = $result['products'] + $result['variations'];

        if ($updated === 0) {
            return back()->with('info', __('No purchase costs were changed.'));
        }

        return back()->with('success', trans_choice(
            '{1} Purchase cost saved for :count item. Run the profit recalculation to apply it to past sales.'
            .'|[2,*] Purchase costs saved for :count items. Run the profit recalculation to apply them to past sales.',
            $updated,
            ['count' => $updated],
        ));
    }
}
