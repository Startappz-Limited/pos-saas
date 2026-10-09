<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Note `shop_id` is the shop that OWNS the product, which is how per-shop
     * revenue is attributed — see .claude/skills/pos-domain-rules. Reports scope by
     * this column, so a sale with no items is invisible to a shop-filtered report.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 10);
        $unitPrice = fake()->randomFloat(2, 50, 2000);
        $unitCost = round($unitPrice * fake()->randomFloat(2, 0.4, 0.8), 2);

        $lineTotal = round($quantity * $unitPrice, 2);
        $totalCost = round($quantity * $unitCost, 2);
        $profit = round($lineTotal - $totalCost, 2);

        return [
            'uuid' => (string) Str::uuid(),
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'shop_id' => Shop::factory(),
            'variation_id' => null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => 0,
            'line_total' => $lineTotal,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'profit' => $profit,
            'profit_margin' => $lineTotal > 0 ? round(($profit / $lineTotal) * 100, 2) : 0,
            'status' => 'completed',
        ];
    }

    /**
     * Attribute the line to a specific shop — the shop that owns the product.
     */
    public function forShop(Shop $shop): static
    {
        return $this->state(fn () => [
            'shop_id' => $shop->id,
        ]);
    }

    /**
     * Line values that sum to an exact total, for tests asserting report figures.
     */
    public function withTotals(float $lineTotal, float $totalCost): static
    {
        return $this->state(fn () => [
            'quantity' => 1,
            'unit_price' => $lineTotal,
            'line_total' => $lineTotal,
            'unit_cost' => $totalCost,
            'total_cost' => $totalCost,
            'profit' => round($lineTotal - $totalCost, 2),
            'profit_margin' => $lineTotal > 0 ? round((($lineTotal - $totalCost) / $lineTotal) * 100, 2) : 0,
        ]);
    }
}
