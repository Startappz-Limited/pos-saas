<?php

namespace App\Http\Controllers;

use App\Actions\Product\SyncProductFromEcommerce;
use App\Actions\Product\SyncProductToEcommerce;
use App\Jobs\SyncProductsJob;
use App\Models\Product;
use App\Models\Shop;
use App\Rules\ExistsForViewer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProductSyncController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private SyncProductToEcommerce $syncToEcommerce,
        private SyncProductFromEcommerce $syncFromEcommerce
    ) {}

    /**
     * Initiate bulk product sync from products index page
     */
    public function initiate(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'shop_id' => ['required', new ExistsForViewer(Shop::class)],
            'platform' => 'required|in:woocommerce,shopify',
            'direction' => 'required|in:from-platform,to-platform,both',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $shop = Shop::findOrFail($request->shop_id);

        // Check if shop has the selected platform enabled
        if (! $shop->isIntegrationEnabled($request->platform)) {
            return back()->with('error', ucfirst($request->platform).' is not enabled for this shop.');
        }

        try {
            // Dispatch job to queue
            SyncProductsJob::dispatch(
                $shop,
                $request->platform,
                $request->direction,
                ['limit' => $request->limit ?? 100],
                auth()->user()?->id
            );

            $shopName = $shop->name;
            $platform = ucfirst($request->platform);
            $directionText = match ($request->direction) {
                'from-platform' => 'import from',
                'to-platform' => 'export to',
                'both' => 'two-way sync with',
            };

            return back()->with('success', "Product sync job queued successfully. Will {$directionText} {$platform} for {$shopName}. Check logs for progress.");
        } catch (\Exception $e) {
            Log::error('Failed to queue product sync job', [
                'shop_id' => $shop->id,
                'platform' => $request->platform,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to queue sync: '.$e->getMessage());
        }
    }

    /**
     * Sync a single product to/from e-commerce platform
     */
    public function syncSingle(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('view', $product);

        $validator = Validator::make($request->all(), [
            'shop_id' => ['required', new ExistsForViewer(Shop::class)],
            'platform' => 'required|in:woocommerce,shopify',
            'platform_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $shop = Shop::findOrFail($request->shop_id);

        // Check if shop has the selected platform enabled
        if (! $shop->isIntegrationEnabled($request->platform)) {
            return back()->with('error', ucfirst($request->platform).' is not enabled for this shop.');
        }

        try {
            $options = [];
            if ($request->filled('platform_name')) {
                $options['platform_name'] = $request->platform_name;
            }

            $result = $this->syncToEcommerce->execute($product, $shop, $request->platform, $options);

            if ($result['success']) {
                return back()->with('success', "Product synced to {$request->platform} successfully.");
            }

            return back()->with('error', 'Sync failed: '.($result['error'] ?? 'Unknown error'));
        } catch (\Exception $e) {
            Log::error('Single product sync failed', [
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => $request->platform,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Sync failed: '.$e->getMessage());
        }
    }
}
