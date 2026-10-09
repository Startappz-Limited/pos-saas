<?php

namespace App\Actions\Product;

use App\Actions\Product\Concerns\GuardsPurchaseCost;
use App\Enums\ProductStatus;
use App\Models\Product;
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

        unset($data['variations']);
        unset($data['sync_shops'], $data['shop_ids']);

        if (array_key_exists('image', $data)) {
            if ($data['image'] instanceof UploadedFile) {
                $data['image'] = $data['image']->store('products', 'public');
            } elseif (blank($data['image'])) {
                unset($data['image']);
            }
        }

        $data['uuid'] = (string) Str::uuid();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['sku'] = filled($data['sku'] ?? null) ? $data['sku'] : Product::generateSku();
        // Left NULL rather than 0 when omitted: an unknown purchase cost must
        // stay visibly unknown so it surfaces on the purchase-cost screen
        // instead of silently costing the sale at zero.
        $data['cost_price'] = $data['cost_price'] ?? null;
        $data['status'] = $data['status'] ?? ProductStatus::ACTIVE;
        $data['created_by'] = $data['created_by'] ?? auth()->id();

        $product = Product::create($data);

        if ($shouldSyncShops) {
            $product->shops()->sync($shopIds);
        }

        if ($product->has_variations && ! empty($variations)) {
            foreach ($variations as $variationData) {
                $product->variations()->create([
                    'uuid' => (string) Str::uuid(),
                    'name' => $variationData['name'],
                    'sku' => $variationData['sku'],
                    'barcode' => $variationData['barcode'] ?? null,
                    'attributes' => [],
                    'cost_price' => $variationData['cost_price'] ?? null,
                    'selling_price' => $variationData['selling_price'],
                    'wholesale_price' => $variationData['wholesale_price'] ?? null,
                    'tax_class' => $variationData['tax_class'] ?? null,
                    'stock_quantity' => $variationData['stock_quantity'] ?? 0,
                    'status' => ProductStatus::ACTIVE,
                ]);
            }
        }

        return $product;
    }
}
