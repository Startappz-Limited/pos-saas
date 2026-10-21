<?php

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Support\Onboarding\OnboardingChecklist;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TwoBusinesses;

/**
 * Onboarding (stage 3): the checklist ticks itself off from the business's
 * data; the CSV import is all-or-nothing, within the business, and its
 * opening stock goes through the stock ledger.
 */
beforeEach(function () {
    Storage::fake('public');
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->a = $this->fixture->a;
    $this->b = $this->fixture->b;
    $this->tenant = app(TenantContext::class);
});

function csvUpload(string $contents): UploadedFile
{
    return UploadedFile::fake()->createWithContent('products.csv', $contents);
}

function importAs($test, $user, $shop, string $csv)
{
    return $test->actingAs($user)->post(route('products.import.store'), ['shop_id' => $shop->id, 'file' => csvUpload($csv)]);
}

it('imports products with a category, a cost and opening stock', function () {
    $shop = $this->a->shops[0];
    $csv = "name,selling_price,sku,category,cost_price,stock\nWidget,250.00,W-1,Tools,150,20\nGadget,99,G-1,Tools,,0\n";

    importAs($this, $this->a->owner, $shop, $csv)->assertRedirect(route('products.index'))->assertSessionHas('success');

    $this->tenant->run($this->a->business, function () use ($shop): void {
        $widget = Product::query()->where('sku', 'W-1')->firstOrFail();
        expect($widget->selling_price)->toEqual('250.00')
            ->and($widget->category->name)->toBe('Tools')
            ->and(Product::query()->where('sku', 'G-1')->exists())->toBeTrue()
            ->and(Category::query()->where('name', 'Tools')->count())->toBe(1)
            ->and(StockMovement::query()->where('shop_id', $shop->id)->where('quantity', 20)->exists())->toBeTrue();
    });
    expect($this->tenant->run($this->b->business, fn () => Product::query()->where('sku', 'W-1')->exists()))->toBeFalse();
});

it('imports nothing when any line is wrong, and says which', function () {
    $csv = "name,selling_price,stock\nGood,10,1\nBad,abc,1\n,5,1\n";

    importAs($this, $this->a->owner, $this->a->shops[0], $csv)
        ->assertSessionHas('import_errors', fn (array $errors): bool => collect($errors)->contains(fn (string $e): bool => str_contains($e, 'Line 3'))
            && collect($errors)->contains(fn (string $e): bool => str_contains($e, 'Line 4')));

    expect($this->tenant->run($this->a->business, fn () => Product::query()->where('name', 'Good')->exists()))->toBeFalse();
});

it('refuses a repeated SKU in the file or one already in the business', function () {

    importAs($this, $this->a->owner, $this->a->shops[0], "name,selling_price,sku\nOne,1,X-1\nTwo,2,x-1\n")
        ->assertSessionHas('import_errors');
    importAs($this, $this->a->owner, $this->a->shops[0], "name,selling_price,sku\nThree,3,SKU-1\n")
        ->assertSessionHas('import_errors');
});

it('requires the name and selling_price columns', function () {
    importAs($this, $this->a->owner, $this->a->shops[0], "title,price\nA,1\n")->assertSessionHas('import_errors');
});

it('keeps another business\'s shop out of reach, and needs the permission to create products', function () {
    importAs($this, $this->a->owner, $this->b->shops[0], "name,selling_price\nA,1\n")->assertSessionHasErrors('shop_id');
    importAs($this, $this->fixture->a->cashier, $this->a->shops[0], "name,selling_price\nA,1\n")->assertForbidden();
});

it('lists the steps and ticks them off from the business\'s data', function () {
    $steps = fn () => $this->tenant->run($this->a->business, fn () => collect(app(OnboardingChecklist::class)->steps())->pluck('done', 'key')->all());

    expect($steps())->toMatchArray(['shop' => true, 'products' => true, 'sale' => true]);

    $this->actingAs($this->a->owner)->get(route('dashboard'))->assertOk();
});

it('offers the first sale only once the business has a shop to sell from', function () {
    $business = Business::factory()->create();
    $keys = fn () => $this->tenant->run($business, fn () => collect(app(OnboardingChecklist::class)->steps())->pluck('key')->all());

    expect($keys())->toBe(['business', 'shop', 'register', 'products']);

    $this->tenant->run($business, fn () => Shop::factory()->create());

    expect($keys())->toBe(['business', 'shop', 'register', 'products', 'sale']);
});

it('shows the checklist to a new owner until everything is done', function () {
    $this->tenant->run($this->a->business, fn () => Product::query()->delete());

    $this->actingAs($this->a->owner)->get(route('dashboard'))->assertOk()->assertSee('Get started')->assertSee('Add your products');
});

it('offers a template that matches what the import reads', function () {
    $response = $this->actingAs($this->a->owner)->get(route('products.import.template'))->assertOk();

    expect($response->streamedContent())->toStartWith('name,selling_price,sku');
});
