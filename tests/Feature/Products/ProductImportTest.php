<?php

use App\Models\Business;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopVariation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

/**
 * Importing products (fix/product-stock-import): the button on the products
 * list; the page's column table and template; column names matched loosely
 * and through alternatives; a missing required column or a bad line refuses
 * the file; ignored columns are a warning; products already in the system (by
 * SKU) are confirmed first, and updating SETS their stock in the chosen shop.
 */
beforeEach(function () {
    seedRolesAndPermissions();
    Storage::fake(TenantFiles::PRIVATE_DISK);
    $this->owner = User::factory()->create();
    $this->owner->assignRole(ownerRole());
    $this->shop = Shop::factory()->create(['name' => 'Main Street']);
    $this->tenant = app(TenantContext::class);
});

function productCsv(string $csv): UploadedFile
{
    return UploadedFile::fake()->createWithContent('products.csv', $csv);
}

function uploadProducts($test, User $user, Shop $shop, string $csv)
{
    app(TenantContext::class)->forget();

    return $test->actingAs($user)->post(route('products.import.store'), ['shop_id' => $shop->id, 'file' => productCsv($csv)]);
}

/**
 * Stock of $product's default variation in $shop.
 */
function stockIn(Product $product, Shop $shop): int
{
    return (int) DB::table('shop_variation')->where('shop_id', $shop->id)
        ->where('variation_id', $product->defaultVariation()->first()->id)->value('stock_quantity');
}

function productNamed(string $name): Product
{
    return Product::query()->where('name', $name)->firstOrFail();
}

describe('the page', function () {
    it('shows an Import button on the products list to those who may import', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $this->actingAs($this->owner)->get(route('products.index'))->assertOk()->assertSee(route('products.import.create'), false);
        $this->tenant->forget();
        $this->actingAs($cashier)->get(route('products.index'))->assertOk()->assertDontSee(route('products.import.create'), false);
    });

    it('lists the required and optional columns with the names also accepted, and the template', function () {
        $this->actingAs($this->owner)->get(route('products.import.create'))->assertOk()
            ->assertSee('<code>name</code>', false)->assertSee('<code>selling_price</code>', false)
            ->assertSeeText('Required')->assertSeeText('Optional')
            ->assertSeeText('stock_quantity, quantity, qty')
            ->assertSeeText('low_stock_threshold, threshold')
            ->assertSee(route('products.import.template'), false);
    });

    it('offers a template with every column and two example rows, which imports as is', function () {
        $template = $this->actingAs($this->owner)->get(route('products.import.template'))->assertOk()->streamedContent();
        $lines = array_values(array_filter(explode("\n", $template)));

        expect($lines[0])->toBe('name,selling_price,sku,barcode,category,cost_price,wholesale_price,stock,reorder_level,unit')
            ->and($lines)->toHaveCount(3);

        uploadProducts($this, $this->owner, $this->shop, $template)->assertRedirect(route('products.index'))->assertSessionHas('success');
        expect(stockIn(productNamed('Wireless Mouse'), $this->shop))->toBe(20)
            ->and(productNamed('Wireless Mouse')->reorder_level)->toBe(5)
            ->and(Product::query()->where('name', 'USB Cable 1m')->exists())->toBeTrue();
    });
});

describe('columns', function () {
    it('reads the stock under any accepted name', function (string $header) {
        uploadProducts($this, $this->owner, $this->shop, "name,selling_price,{$header}\nTea,100,12\n")->assertSessionHas('success');

        expect(stockIn(productNamed('Tea'), $this->shop))->toBe(12);
    })->with(['stock', 'stock_quantity', 'quantity', 'qty', 'opening_stock', 'Stock Quantity', 'QTY']);

    it('reads the threshold under any accepted name', function (string $header) {
        uploadProducts($this, $this->owner, $this->shop, "name,selling_price,{$header}\nTea,100,7\n")->assertSessionHas('success');

        expect(productNamed('Tea')->reorder_level)->toBe(7);
    })->with(['reorder_level', 'low_stock_threshold', 'Low Stock Threshold', 'threshold', 'min_stock']);

    it('matches the headers a spreadsheet typically has', function () {
        uploadProducts($this, $this->owner, $this->shop, "Product Name,Price,Stock Quantity,Low Stock Threshold,Cost\nCoffee,350,40,6,200\n")
            ->assertSessionHas('success');

        $coffee = productNamed('Coffee');
        expect(stockIn($coffee, $this->shop))->toBe(40)
            ->and($coffee->reorder_level)->toBe(6)
            ->and((float) $coffee->selling_price)->toBe(350.0)
            ->and(DB::table('stock_movements')->where('product_id', $coffee->id)->count())->toBe(1);
    });

    it('refuses the whole file when a required column is missing, naming it', function () {
        uploadProducts($this, $this->owner, $this->shop, "name,stock\nTea,5\n")
            ->assertSessionHas('import_errors', fn (array $errors): bool => str_contains($errors[0], 'The file needs a "selling_price" column'));

        expect(Product::query()->where('name', 'Tea')->exists())->toBeFalse();
    });

    it('refuses two columns that mean the same thing', function () {
        uploadProducts($this, $this->owner, $this->shop, "name,selling_price,stock,qty\nTea,100,5,6\n")
            ->assertSessionHas('import_errors', fn (array $errors): bool => str_contains($errors[0], 'Two columns mean "stock"'));
    });

    it('imports the rest and warns about columns it doesn\'t use', function () {
        uploadProducts($this, $this->owner, $this->shop, "name,selling_price,colour,supplier\nTea,100,green,Acme\n")
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('warning', 'Ignored columns: colour, supplier. Their values were not imported.');

        expect(Product::query()->where('name', 'Tea')->exists())->toBeTrue();
    });

    it('refuses the file over one bad line, saying which', function () {
        uploadProducts($this, $this->owner, $this->shop, "name,selling_price,stock\nTea,100,5\nCoffee,abc,2\n")
            ->assertSessionHas('import_errors', fn (array $errors): bool => str_contains($errors[0], 'Line 3'));

        expect(Product::query()->whereIn('name', ['Tea', 'Coffee'])->exists())->toBeFalse();
    });

    it('says how many products and units of opening stock went in', function () {
        uploadProducts($this, $this->owner, $this->shop, "name,selling_price,stock\nTea,100,5\nCoffee,200,7\n")
            ->assertSessionHas('success', '2 new products imported into Main Street, with 12 units of opening stock.');
    });
});

describe('products already in the system', function () {
    beforeEach(function () {
        uploadProducts($this, $this->owner, $this->shop, "name,selling_price,sku,stock,reorder_level\nTea,100,TEA-1,10,4\n")->assertSessionHas('success');
        $this->tea = productNamed('Tea');
    });

    it('asks first, changing nothing, and shows current against the file', function () {
        uploadProducts($this, $this->owner, $this->shop, "name,selling_price,sku,stock,reorder_level\nTea renamed,999,tea-1,25,8\nCoffee,200,COF-1,3,2\n")
            ->assertRedirect(route('products.import.confirm'));

        expect(stockIn($this->tea, $this->shop))->toBe(10)
            ->and(Product::query()->where('name', 'Coffee')->exists())->toBeFalse();

        $this->tenant->forget();
        $this->actingAs($this->owner)->get(route('products.import.confirm'))->assertOk()
            ->assertSeeText('1 product whose SKU already exists, and 1 new one')
            ->assertSee('10', false)->assertSee('<strong>25</strong>', false)->assertSee('<strong>8</strong>', false)
            ->assertSeeText('Will change');
    });

    it('updates by setting stock to the file\'s figure in the chosen shop, imports the new ones, and keeps names and prices', function () {
        uploadProducts($this, $this->owner, $this->shop, "name,selling_price,sku,stock,reorder_level\nTea renamed,999,TEA-1,25,8\nCoffee,200,COF-1,3,2\n");

        $this->tenant->forget();
        $this->actingAs($this->owner)->post(route('products.import.decide'), ['decision' => 'update'])
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('success', '1 new product imported into Main Street, with 3 units of opening stock. 1 existing product updated (stock set to the file\'s figure).');

        $tea = $this->tea->fresh();
        expect(stockIn($tea, $this->shop))->toBe(25)
            ->and(ShopVariation::query()->where('shop_id', $this->shop->id)->where('variation_id', $tea->defaultVariation()->first()->id)->value('reorder_level'))->toBe(8)
            ->and($tea->name)->toBe('Tea')
            ->and((float) $tea->selling_price)->toBe(100.0)
            ->and(stockIn(productNamed('Coffee'), $this->shop))->toBe(3)
            ->and(session('product_import.pending'))->toBeNull()
            ->and(Storage::disk(TenantFiles::PRIVATE_DISK)->allFiles())->toBe([]);
    });

    it('leaves stock as it is when the same file is uploaded again: 10 stays 10, not 20', function () {
        uploadProducts($this, $this->owner, $this->shop, "name,selling_price,sku,stock,reorder_level\nTea,100,TEA-1,10,4\n")
            ->assertRedirect(route('products.import.confirm'));

        $this->tenant->forget();
        $this->actingAs($this->owner)->get(route('products.import.confirm'))->assertSeeText('No change');
        $this->tenant->forget();
        $this->actingAs($this->owner)->post(route('products.import.decide'), ['decision' => 'update'])
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, '1 existing product already matched the file'));

        expect(stockIn($this->tea, $this->shop))->toBe(10)
            ->and(DB::table('stock_movements')->where('product_id', $this->tea->id)->count())->toBe(1);
    });

    it('lowers stock too, recording the adjustment', function () {
        uploadProducts($this, $this->owner, $this->shop, "name,selling_price,sku,stock\nTea,100,TEA-1,6\n");
        $this->tenant->forget();
        $this->actingAs($this->owner)->post(route('products.import.decide'), ['decision' => 'update']);

        expect(stockIn($this->tea, $this->shop))->toBe(6)
            ->and(DB::table('stock_movements')->where('product_id', $this->tea->id)->count())->toBe(2);
    });

    it('leaves an existing product\'s stock alone when the file\'s cell is empty', function () {
        uploadProducts($this, $this->owner, $this->shop, "name,selling_price,sku,stock,reorder_level\nTea,100,TEA-1,,9\n");
        $this->tenant->forget();
        $this->actingAs($this->owner)->post(route('products.import.decide'), ['decision' => 'update']);

        expect(stockIn($this->tea, $this->shop))->toBe(10);
    });

    it('imports only the new ones when asked, leaving existing products as they were', function () {
        uploadProducts($this, $this->owner, $this->shop, "name,selling_price,sku,stock\nTea,100,TEA-1,50\nCoffee,200,COF-1,3\n");
        $this->tenant->forget();
        $this->actingAs($this->owner)->post(route('products.import.decide'), ['decision' => 'new_only'])
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, '1 existing product left as it was'));

        expect(stockIn($this->tea, $this->shop))->toBe(10)
            ->and(Product::query()->where('name', 'Coffee')->exists())->toBeTrue();
    });

    it('changes nothing when cancelled, and deletes the held file', function () {
        uploadProducts($this, $this->owner, $this->shop, "name,selling_price,sku,stock\nTea,100,TEA-1,50\nCoffee,200,COF-1,3\n");
        expect(Storage::disk(TenantFiles::PRIVATE_DISK)->allFiles())->toHaveCount(1);

        $this->tenant->forget();
        $this->actingAs($this->owner)->post(route('products.import.decide'), ['decision' => 'cancel'])
            ->assertRedirect(route('products.import.create'))->assertSessionHas('info');

        expect(stockIn($this->tea, $this->shop))->toBe(10)
            ->and(Product::query()->where('name', 'Coffee')->exists())->toBeFalse()
            ->and(Storage::disk(TenantFiles::PRIVATE_DISK)->allFiles())->toBe([]);
    });

    it('lets the held upload expire after 30 minutes', function () {
        uploadProducts($this, $this->owner, $this->shop, "name,selling_price,sku,stock\nTea,100,TEA-1,50\n");

        $this->travel(31)->minutes();
        $this->tenant->forget();
        $this->actingAs($this->owner)->post(route('products.import.decide'), ['decision' => 'update'])
            ->assertRedirect(route('products.import.create'))->assertSessionHas('error');

        expect(stockIn($this->tea, $this->shop))->toBe(10)
            ->and(Storage::disk(TenantFiles::PRIVATE_DISK)->allFiles())->toBe([]);
    });

    it('sets the stock in the shop chosen on the import page', function () {
        $branch = Shop::factory()->create(['name' => 'Branch']);
        uploadProducts($this, $this->owner, $branch, "name,selling_price,sku,stock\nTea,100,TEA-1,7\n");
        $this->tenant->forget();
        $this->actingAs($this->owner)->post(route('products.import.decide'), ['decision' => 'update']);

        expect(stockIn($this->tea, $branch))->toBe(7)
            ->and(stockIn($this->tea, $this->shop))->toBe(10);
    });

    it('treats another business\'s SKU as new', function () {
        $other = Business::factory()->create();
        $this->tenant->run($other, fn () => Product::factory()->create(['sku' => 'OTHER-1', 'shop_id' => Shop::factory()->create()->id]));

        uploadProducts($this, $this->owner, $this->shop, "name,selling_price,sku\nMine,100,OTHER-1\n")
            ->assertRedirect(route('products.index'))->assertSessionHas('success');

        expect(Product::query()->where('name', 'Mine')->exists())->toBeTrue();
    });
});

describe('who may import', function () {
    it('needs products.import, not just the right to create products', function () {
        Permission::findOrCreate('products.create');
        $creator = User::factory()->create();
        $creator->givePermissionTo(['products.view', 'products.create']);

        foreach ([['get', route('products.import.create')], ['get', route('products.import.template')]] as [$method, $url]) {
            $this->tenant->forget();
            $this->actingAs($creator)->{$method}($url)->assertForbidden();
        }
        uploadProducts($this, $creator, $this->shop, "name,selling_price\nTea,1\n")->assertForbidden();

        // An inventory clerk holds products.import, and works in the shop.
        $clerk = User::factory()->create();
        $clerk->assignRole('inventory-clerk');
        $clerk->shops()->attach($this->shop->id);
        uploadProducts($this, $clerk, $this->shop, "name,selling_price\nTea,1\n")->assertSessionHas('success');
    });
});
