<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleSource;
use App\Models\Shop;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * A sale-level discount may not exceed the subtotal. CalculateSaleTotals doesn't
 * clamp it, so a larger one used to record a negative total — and, on credit, a
 * negative balance that collectPayment's min:0.01 then refused forever.
 */
beforeEach(function () {
    Permission::findOrCreate('sales.full-access');

    $this->shop = Shop::factory()->create(['code' => 'NRB', 'vat_registered' => false]);
    $this->user = User::factory()->create();
    $this->user->shops()->attach($this->shop);
    $this->user->givePermissionTo('sales.full-access');
    SaleSource::create(['name' => 'Counter', 'is_active' => true, 'sort_order' => 1]);

    $this->product = Product::factory()->create([
        'shop_id' => $this->shop->id,
        'selling_price' => 50,
        'cost_price' => 30,
        'stock_quantity' => 100,
        'status' => 'active',
    ]);
});

function discountSalePayload(Product $product, float $discount): array
{
    return [
        'source_id' => SaleSource::first()->id,
        'delivery_location' => 'Front counter',
        'walk_in_customer_name' => 'Walk In',
        'walk_in_customer_phone' => '0712345678',
        'payment_method' => 'cash',
        // Subtotal 2 x 50 = 100.
        'items' => [['product_id' => $product->id, 'quantity' => 2, 'price' => 50]],
        'discount_amount' => $discount,
    ];
}

it('refuses a discount larger than the subtotal on the web till', function () {
    $this->actingAs($this->user)
        ->post(route('sales.store'), discountSalePayload($this->product, 100.01))
        ->assertSessionHasErrors(['discount_amount' => 'The discount cannot be more than the sale subtotal (100.00).']);

    expect(Sale::count())->toBe(0);
});

it('refuses a discount larger than the subtotal on the API', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/sales', discountSalePayload($this->product, 200))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['discount_amount']);

    expect(Sale::count())->toBe(0);
});

it('accepts a discount equal to the subtotal', function () {
    $this->actingAs($this->user)
        ->post(route('sales.store'), discountSalePayload($this->product, 100))
        ->assertSessionHasNoErrors();

    expect((float) Sale::sole()->total_amount)->toBe(0.0);
});
