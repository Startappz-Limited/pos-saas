<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Scopes\ShopAccessScope;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $costPrice = fake()->randomFloat(2, 10, 100);
        $sellingPrice = $costPrice * fake()->randomFloat(2, 1.2, 2.0);

        return [
            // Products always belong to a shop; without one they are visible to
            // no one but a super-admin. Reuses the first shop rather than
            // creating one per product, so shop counts in tests stay stable.
            'shop_id' => fn (): int => Shop::withoutGlobalScope(ShopAccessScope::class)->orderBy('id')->value('id')
                ?? Shop::factory()->create()->id,
            'uuid' => (string) Str::uuid(),
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(10),
            'sku' => 'SKU-'.strtoupper(Str::random(8)),
            'category_id' => Category::factory(),
            'barcode' => fake()->optional()->ean13(),
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
            'stock_quantity' => fake()->numberBetween(0, 100),
            'reorder_level' => 10,
            'track_stock' => true,
            'has_variations' => false,
            'image' => null,
            'images' => null,
            'unit' => fake()->randomElement(['piece', 'kg', 'liter', 'meter']),
            'status' => ProductStatus::ACTIVE,
            'notes' => fake()->optional()->sentence(),
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::ACTIVE,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::INACTIVE,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::OUT_OF_STOCK,
            'stock_quantity' => 0,
        ]);
    }

    public function discontinued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::DISCONTINUED,
        ]);
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => fake()->numberBetween(1, 9),
            'reorder_level' => 10,
        ]);
    }

    public function withVariations(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_variations' => true,
        ]);
    }

    /**
     * A product whose purchase cost has never been established — the state
     * every e-commerce import now lands in.
     */
    public function withoutPurchaseCost(): static
    {
        return $this->state(fn (array $attributes) => [
            'cost_price' => null,
        ]);
    }

    /**
     * A product carrying the old importer bug: a "cost" copied from a
     * pre-discount selling price, so every sale reports a loss.
     */
    public function costAboveSellingPrice(): static
    {
        return $this->state(fn (array $attributes) => [
            'cost_price' => 2400,
            'selling_price' => 1999,
        ]);
    }
}
