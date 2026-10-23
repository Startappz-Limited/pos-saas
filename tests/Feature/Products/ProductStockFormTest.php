<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Models\ShopVariation;
use App\Models\User;
use App\Services\Stock\StockLedger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Stock and the low-stock threshold on the product form, and on the API
 * import (fix/product-stock-import). The form sent the threshold as
 * `low_stock_threshold`, which the server dropped; the edit form showed the
 * stock of every shop, which saving copied into the owning shop.
 */
beforeEach(function () {
    seedRolesAndPermissions();
    $this->owner = User::factory()->create();
    $this->owner->assignRole(ownerRole());
    $this->shop = Shop::factory()->create(['name' => 'Main Street']);
    $this->branch = Shop::factory()->create(['name' => 'Branch']);
    $this->category = Category::factory()->create();
    $this->tenant = app(TenantContext::class);
    $this->tenant->forget();
});

function productForm(array $overrides = []): array
{
    return [
        'name' => 'Form Tea', 'shop_id' => test()->shop->id, 'category_id' => test()->category->id,
        'selling_price' => 100, 'cost_price' => 60, ...$overrides,
    ];
}

function shopStock(Product $product, Shop $shop): int
{
    return (int) DB::table('shop_variation')->where('shop_id', $shop->id)
        ->where('variation_id', $product->defaultVariation()->first()->id)->value('stock_quantity');
}

it('saves the opening stock in the owning shop and the threshold, on create', function () {
    $this->actingAs($this->owner)->post(route('products.store'), productForm(['stock_quantity' => 15, 'reorder_level' => 4]))
        ->assertSessionHasNoErrors();

    $product = Product::query()->where('name', 'Form Tea')->firstOrFail();
    expect(shopStock($product, $this->shop))->toBe(15)
        ->and($product->reorder_level)->toBe(4)
        ->and(DB::table('stock_movements')->where('product_id', $product->id)->count())->toBe(1);
});

it('still accepts the old low_stock_threshold field', function () {
    $this->actingAs($this->owner)->post(route('products.store'), productForm(['low_stock_threshold' => 3]))->assertSessionHasNoErrors();

    expect(Product::query()->where('name', 'Form Tea')->firstOrFail()->reorder_level)->toBe(3);
});

it('names the threshold field as the server reads it, on both forms', function () {
    $this->actingAs($this->owner)->get(route('products.create'))->assertOk()
        ->assertSee('name="reorder_level"', false)->assertDontSee('name="low_stock_threshold"', false)
        ->assertSeeText('Opening stock');
});

it('shows and saves the owning shop\'s stock and the real threshold on edit, leaving other shops alone', function () {
    $this->actingAs($this->owner)->post(route('products.store'), productForm(['stock_quantity' => 10, 'reorder_level' => 4]));
    $product = Product::query()->where('name', 'Form Tea')->firstOrFail();
    $product->shops()->syncWithoutDetaching([$this->shop->id, $this->branch->id]);
    $product->syncShopVariationRows();
    app(StockLedger::class)->setLevel($this->branch->id, $product->defaultVariation()->first(), 25);

    // The form shows Main Street's 10, not the 35 across both shops.
    $this->tenant->forget();
    $html = $this->actingAs($this->owner)->get(route('products.edit', $product))->assertOk()
        ->assertSeeText('Stock in Main Street')->getContent();
    expect($html)->toMatch('/id="stock_quantity" name="stock_quantity"[^>]*value="10"/')
        ->and($html)->toMatch('/id="reorder_level" name="reorder_level"[^>]*value="4"/');

    // Saving it unchanged changes no shop's stock; the threshold saves.
    $this->tenant->forget();
    $this->actingAs($this->owner)->put(route('products.update', $product), productForm(['stock_quantity' => 10, 'reorder_level' => 7]))
        ->assertSessionHasNoErrors();

    expect(shopStock($product, $this->shop))->toBe(10)
        ->and(shopStock($product, $this->branch))->toBe(25)
        ->and($product->fresh()->reorder_level)->toBe(7);
});

it('shows the threshold on the product page', function () {
    $this->actingAs($this->owner)->post(route('products.store'), productForm(['reorder_level' => 6]));
    $product = Product::query()->where('name', 'Form Tea')->firstOrFail();

    $this->tenant->forget();
    $this->actingAs($this->owner)->get(route('products.show', $product))->assertOk()->assertSeeText('6 units');
});

it('saves a variation\'s threshold where the low-stock check reads it', function () {
    $this->actingAs($this->owner)->post(route('products.store'), productForm([
        'name' => 'Sized Tea', 'has_variations' => 1,
        'variations' => [['name' => 'Small', 'sku' => 'TEA-S', 'selling_price' => 90, 'stock_quantity' => 11, 'reorder_level' => 2]],
    ]))->assertSessionHasNoErrors();

    $variation = ProductVariation::query()->where('sku', 'TEA-S')->firstOrFail();
    expect(ShopVariation::query()->where('shop_id', $this->shop->id)->where('variation_id', $variation->id)->value('reorder_level'))->toBe(2);
});

describe('the /api/v1 import', function () {
    beforeEach(function () {
        $token = $this->owner->createToken('test', ['*']);
        $token->accessToken->forceFill(['business_id' => $this->owner->default_business_id])->save();
        $this->v1 = $token->plainTextToken;
    });

    it('imports, reporting the opening stock and the ignored columns', function () {
        $this->withToken($this->v1)->post('/api/v1/products/import', [
            'shop' => $this->shop->fresh()->uuid,
            'file' => UploadedFile::fake()->createWithContent('p.csv', "name,price,qty,colour\nApi Tea,100,8,red\n"),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.imported', 1)->assertJsonPath('data.opening_stock', 8)->assertJsonPath('data.ignored_columns', ['colour']);
    });

    it('refuses a file holding an SKU that already exists (no confirmation screen here)', function () {
        $this->tenant->run($this->owner->default_business_id, fn () => Product::factory()->create(['sku' => 'TEA-1', 'shop_id' => $this->shop->id]));
        $this->tenant->forget();

        $this->withToken($this->v1)->post('/api/v1/products/import', [
            'shop' => $this->shop->fresh()->uuid,
            'file' => UploadedFile::fake()->createWithContent('p.csv', "name,selling_price,sku\nTea,100,TEA-1\n"),
        ], ['Accept' => 'application/json'])->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Line 2: the SKU "TEA-1" already exists. Update its stock with a stock adjustment, or through the import page.');
    });
});
