<?php

namespace App\Actions\Product;

use App\Actions\Product\Concerns\GuardsPurchaseCost;
use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UpdateProductAction
{
    use GuardsPurchaseCost;

    public function execute(Product $product, array $data): Product
    {
        $data = $this->filterPurchaseCost($data);

        $variations = $data['variations'] ?? null;
        $shouldSyncShops = array_key_exists('sync_shops', $data) || array_key_exists('shop_ids', $data);
        $shopIds = $data['shop_ids'] ?? [];

        unset($data['variations']);
        unset($data['sync_shops'], $data['shop_ids']);

        if (array_key_exists('image', $data)) {
            if ($data['image'] instanceof UploadedFile) {
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }

                $data['image'] = $data['image']->store('products', 'public');
            } elseif (blank($data['image'])) {
                unset($data['image']);
            }
        }

        // Update slug if name changed and no custom slug provided
        if (isset($data['name']) && $data['name'] !== $product->name && ! isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $data['updated_by'] = $data['updated_by'] ?? auth()->id();

        $product->update($data);

        if ($shouldSyncShops) {
            $product->shops()->sync($shopIds);
        }

        // Sync variations when has_variations is enabled and variations data is provided
        if ($product->has_variations && $variations !== null) {
            $existingIds = $product->variations()->pluck('id')->toArray();
            $submittedIds = [];

            foreach ($variations as $variationData) {
                if (! empty($variationData['id'])) {
                    // Update existing variation
                    $variation = $product->variations()->find($variationData['id']);
                    if ($variation) {
                        $payload = [
                            'name' => $variationData['name'],
                            'sku' => $variationData['sku'],
                            'barcode' => $variationData['barcode'] ?? null,
                            'selling_price' => $variationData['selling_price'],
                            'wholesale_price' => $variationData['wholesale_price'] ?? null,
                            'tax_class' => $variationData['tax_class'] ?? null,
                            'stock_quantity' => $variationData['stock_quantity'] ?? 0,
                        ];

                        // Absent key means "not submitted" (filtered out for a
                        // user without products.set-cost); keep the stored cost
                        // rather than blanking it.
                        if (array_key_exists('cost_price', $variationData)) {
                            $payload['cost_price'] = $variationData['cost_price'];
                        }

                        $variation->update($payload);
                        $submittedIds[] = $variation->id;
                    }
                } else {
                    // Create new variation
                    $newVariation = $product->variations()->create([
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
                    $submittedIds[] = $newVariation->id;
                }
            }

            // Delete removed variations
            $removedIds = array_diff($existingIds, $submittedIds);
            if (! empty($removedIds)) {
                $product->variations()->whereIn('id', $removedIds)->delete();
            }
        }

        // If has_variations was turned off, remove all variations
        if (! $product->has_variations && $product->variations()->exists()) {
            $product->variations()->delete();
        }

        return $product->fresh();
    }
}
