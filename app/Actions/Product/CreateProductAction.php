<?php

namespace App\Actions\Product;

use App\Actions\Product\Concerns\GuardsPurchaseCost;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantFiles;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CreateProductAction
{
    use GuardsPurchaseCost;

    public function execute(array $data): Product
    {
        $data = $this->filterPurchaseCost($data);

        $variations = $data['variations'] ?? [];
        $shouldSyncShops = array_key_exists('sync_shops', $data) || array_key_exists('shop_ids', $data);
        $shopIds = $data['shop_ids'] ?? [];
        // Not set on the form: the business type's own default (stage 11, step 5).
        $trackBatches = $data['track_batches'] ?? null;

        // Stock changes only through intakes, adjustments, stocktakes and sales
        // (decided after stage 11, step 4): a product is never created with
        // stock through this form, whatever is sent.
        unset($data['variations'], $data['stock_quantity'], $data['track_batches']);
        unset($data['sync_shops'], $data['shop_ids']);

        if (array_key_exists('image', $data)) {
            if ($data['image'] instanceof UploadedFile) {
                // Under the business's folder, within its quota (stage 1c); public,
                // since the app and the online stores load product images unsigned.
                $data['image'] = app(TenantFiles::class)->storePublic($data['image'], 'products');
            } elseif (blank($data['image'])) {
                unset($data['image']);
            }
        }

        $data['uuid'] = (string) Str::uuid();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $skuGenerated = ! filled($data['sku'] ?? null);
        $data['sku'] = $skuGenerated ? Product::generateSku() : $data['sku'];
        // Left NULL rather than 0 when omitted: an unknown purchase cost must
        // stay visibly unknown so it surfaces on the purchase-cost screen
        // instead of silently costing the sale at zero.
        $data['cost_price'] = $data['cost_price'] ?? null;
        $data['status'] = $data['status'] ?? ProductStatus::ACTIVE;
        $data['created_by'] = $data['created_by'] ?? auth()->id();

        // A generated SKU is checked before the insert, which races another request: on a duplicate
        // key, a fresh SKU is generated and the insert tried again (stage 11, step 9).
        $product = $this->createRetryingSku($data, $skuGenerated);

        // New products take the business type's track_batches default (stage 11, step 5); the form
        // can change it. Set on the hidden default variation too, for a product with real ones.
        $trackBatches ??= app(TenantContext::class)->business()->tracksBatchesByDefault();
        $product->defaultVariation()->update(['track_batches' => $trackBatches]);

        if ($shouldSyncShops) {
            $product->shops()->sync($shopIds);
            $product->syncShopVariationRows();
        }

        if ($product->has_variations && ! empty($variations)) {
            foreach ($variations as $variationData) {
                $variation = $product->variations()->create([
                    'uuid' => (string) Str::uuid(),
                    'name' => $variationData['name'],
                    'sku' => $variationData['sku'],
                    'barcode' => $variationData['barcode'] ?? null,
                    'attributes' => [],
                    'cost_price' => $variationData['cost_price'] ?? null,
                    'selling_price' => $variationData['selling_price'],
                    'wholesale_price' => $variationData['wholesale_price'] ?? null,
                    'tax_class' => $variationData['tax_class'] ?? null,
                    'track_batches' => $variationData['track_batches'] ?? $trackBatches,
                    'status' => ProductStatus::ACTIVE,
                ]);

                // A variation's threshold lives on its row in the owning shop.
                if (isset($variationData['reorder_level']) && $variationData['reorder_level'] !== '' && ($shopId = $product->stockShopId()) !== null) {
                    $variation->setShopReorderLevel($shopId, (int) $variationData['reorder_level']);
                }
            }
        }

        return $product;
    }

    /**
     * Inserts the product; a duplicate-key error on a generated SKU retries with a new one.
     *
     * @param  array<string, mixed>  $data
     */
    private function createRetryingSku(array $data, bool $generated): Product
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return Product::create($data);
            } catch (UniqueConstraintViolationException $e) {
                if (! $generated || $attempt >= 5) {
                    throw $e;
                }

                $data['sku'] = Product::generateSku();
            }
        }
    }
}
