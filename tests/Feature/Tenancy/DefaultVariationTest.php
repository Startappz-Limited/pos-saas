<?php

use App\Actions\Product\CreateProductAction;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Every product has a default variation, and per-shop stock rows are keyed on
 * shop and variation (stage 1a acceptance, decision 4.2).
 */
function shopVariationRows(Product $product): array
{
    return DB::table('shop_variation')->where('product_id', $product->id)
        ->orderBy('shop_id')->orderBy('variation_id')
        ->get(['shop_id', 'variation_id'])
        ->map(fn (object $row): array => [(int) $row->shop_id, (int) $row->variation_id])
        ->all();
}

it('creates the default variation with the product', function () {
    $product = Product::factory()->create(['selling_price' => 250]);
    $default = $product->defaultVariation;

    expect($default)->not->toBeNull()
        ->and($default->is_default)->toBeTrue()
        ->and((float) $default->selling_price)->toBe(250.0)
        ->and($default->business_id)->toBe($product->business_id)
        ->and(DB::table('product_variations')->where('product_id', $product->id)->count())->toBe(1);
});

it('keeps the default variation out of variation lists', function () {
    $product = Product::factory()->create();
    $chosen = ProductVariation::factory()->create(['product_id' => $product->id]);

    expect($product->variations()->pluck('id')->all())->toBe([$chosen->id])
        ->and($product->sellableVariations()->count())->toBe(2)
        ->and(ProductVariation::where('product_id', $product->id)->count())->toBe(1);
});

it('allows one default variation per product', function () {
    $product = Product::factory()->create();

    expect(fn () => ProductVariation::create([
        'product_id' => $product->id, 'name' => 'Second default', 'sku' => 'SECOND-DEFAULT', 'is_default' => true,
    ]))->toThrow(QueryException::class);
});

it('keys per-shop stock rows on shop and variation, in every shop of the product', function () {
    [$main, $branch] = Shop::factory()->count(2)->create();

    $product = app(CreateProductAction::class)->execute([
        'name' => 'Protein Bar',
        'category_id' => Category::factory()->create()->id,
        'shop_id' => $main->id,
        'selling_price' => 120,
        'shop_ids' => [$main->id, $branch->id],
    ]);
    $default = $product->defaultVariation;

    expect(shopVariationRows($product))->toBe([[$main->id, $default->id], [$branch->id, $default->id]]);

    // A variation someone adds gets its own row in each shop.
    $chocolate = ProductVariation::factory()->create(['product_id' => $product->id]);

    expect(shopVariationRows($product))->toBe([
        [$main->id, $default->id], [$main->id, $chocolate->id],
        [$branch->id, $default->id], [$branch->id, $chocolate->id],
    ]);
});

it('doesn\'t change what the flat API returns for a simple product', function () {
    $owner = User::factory()->create();
    $owner->assignRole(ownerRole());
    $product = Product::factory()->create(['has_variations' => false]);
    Sanctum::actingAs($owner, ['*']);

    $this->getJson("/api/products/{$product->uuid}")
        ->assertOk()
        ->assertJsonPath('data.variations', []);
});
