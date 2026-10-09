<?php

namespace App\Actions\Product;

use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Support\Facades\DB;

/**
 * Records the purchase cost (COGS basis) for products and variations that did
 * not receive one from a supplier purchase order.
 */
class SetProductPurchaseCostsAction
{
    /**
     * @param  array<int, float|string|null>  $productCosts  keyed by product id
     * @param  array<int, float|string|null>  $variationCosts  keyed by variation id
     * @return array{products: int, variations: int}
     */
    public function execute(array $productCosts = [], array $variationCosts = []): array
    {
        $productCosts = $this->normalise($productCosts);
        $variationCosts = $this->normalise($variationCosts);

        if (empty($productCosts) && empty($variationCosts)) {
            return ['products' => 0, 'variations' => 0];
        }

        return DB::transaction(function () use ($productCosts, $variationCosts): array {
            $products = 0;
            $variations = 0;

            // Saved one model at a time rather than in a mass update so the
            // Auditable trait records who changed each cost and from what.
            foreach (Product::whereIn('id', array_keys($productCosts))->get() as $product) {
                $product->cost_price = $productCosts[$product->id];
                $product->updated_by = auth()->id();

                if ($product->isDirty('cost_price')) {
                    $product->save();
                    $products++;
                }
            }

            foreach (ProductVariation::whereIn('id', array_keys($variationCosts))->get() as $variation) {
                $variation->cost_price = $variationCosts[$variation->id];

                if ($variation->isDirty('cost_price')) {
                    $variation->save();
                    $variations++;
                }
            }

            return ['products' => $products, 'variations' => $variations];
        });
    }

    /**
     * Drop blank entries so a partially filled form only touches the rows the
     * user actually keyed, and cast the rest to a float.
     *
     * @param  array<int, float|string|null>  $costs
     * @return array<int, float>
     */
    private function normalise(array $costs): array
    {
        $normalised = [];

        foreach ($costs as $id => $cost) {
            if ($cost === null || $cost === '' || ! is_numeric($cost)) {
                continue;
            }

            $normalised[(int) $id] = round((float) $cost, 2);
        }

        return $normalised;
    }
}
