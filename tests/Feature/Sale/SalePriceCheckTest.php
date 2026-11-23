<?php

use App\Actions\Sale\RecordSale;
use App\Actions\Sale\SaleContext;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Shop;
use App\Models\SyncIssue;
use App\Models\User;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/*
 * Stage 11b, steps 3 to 5: a sale is checked against the price the server expects. Online (the flat API) a lower price
 * or an unapproved discount above the seller's limit is refused; a till's push records it and puts it on the sync review.
 * A cent per line is tolerated. A discount within the limit is allowed; above it, it needs an approver with
 * sales.override-price.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->shop = Shop::factory()->create();
    $this->source = DB::table('sale_sources')->where('business_id', testBusiness()->id)->value('id');

    // A bottle at 100 (its selling price).
    $this->soda = Product::factory()->create(['shop_id' => $this->shop->id, 'unit' => 'bottle', 'selling_price' => 100, 'stock_quantity' => 0, 'has_variations' => false]);
    app(StockLedger::class)->receive($this->shop->id, $this->soda->defaultVariation, 100, 20, StockMovementType::INTAKE);

    // A cashier (no override, a 0% discount limit) and a manager (override, no limit).
    $this->cashier = User::factory()->create();
    $this->cashier->assignRole(sharedRole('cashier'));
    $this->cashier->shops()->attach($this->shop);

    $this->manager = User::factory()->create();
    $this->manager->assignRole(sharedRole('manager'));
    $this->manager->shops()->attach($this->shop);
});

/** A flat API sale of one bottle at $price, with optional extra sale fields. */
function priceCheckPayload(array $extra = [], float $price = 100, float $quantity = 1): array
{
    return [
        'shop_id' => test()->shop->id,
        'source_id' => test()->source,
        'delivery_location' => 'In store',
        'walk_in_customer_name' => 'Walk In',
        'walk_in_customer_phone' => '0700000000',
        'payment_method' => 'cash',
        'items' => [['product_id' => test()->soda->id, 'quantity' => $quantity, 'price' => $price]],
        ...$extra,
    ];
}

it('refuses a flat API sale below the expected price without sales.override-price, and records nothing', function () {
    Sanctum::actingAs($this->cashier, ['*']);

    $response = $this->postJson('/api/sales', priceCheckPayload(price: 90))
        ->assertStatus(422)
        ->assertJson(['success' => false]);

    // The flat API's own shape: a message, no code. The reason is the price check's.
    expect($response->json('message'))->toContain('The price for '.$this->soda->name.' is below KES 100.00. Ask someone who can override prices.')
        ->and($response->json())->not->toHaveKey('code')
        ->and(DB::table('sales')->count())->toBe(0);
});

it('accepts a flat API sale below the expected price with the permission, and records who allowed it', function () {
    Sanctum::actingAs($this->manager, ['*']);

    $this->postJson('/api/sales', priceCheckPayload(price: 90))->assertCreated();

    $line = DB::table('sale_items')->first();
    expect((float) $line->unit_price)->toBe(90.0)
        ->and((float) $line->expected_unit_price)->toBe(100.0)
        ->and($line->price_overridden_by)->toBe($this->manager->id)
        ->and($line->override_approved_at)->not->toBeNull();
});

it('tolerates a cent on a line, and refuses two', function () {
    Sanctum::actingAs($this->cashier, ['*']);

    $this->postJson('/api/sales', priceCheckPayload(price: 99.99))->assertCreated();
    $this->postJson('/api/sales', priceCheckPayload(price: 99.98))->assertStatus(422);
});

it('accepts a price above the expected one, and records the expected price', function () {
    Sanctum::actingAs($this->cashier, ['*']);

    $this->postJson('/api/sales', priceCheckPayload(price: 120))->assertCreated();

    $line = DB::table('sale_items')->first();
    expect((float) $line->unit_price)->toBe(120.0)
        ->and((float) $line->expected_unit_price)->toBe(100.0)
        ->and($line->price_overridden_by)->toBeNull();
});

it('refuses a discount above the cashier\'s limit online, and accepts one within the manager\'s', function () {
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson('/api/sales', priceCheckPayload(['discount_amount' => 10]))
        ->assertStatus(422)
        ->assertJson(['success' => false]);

    Sanctum::actingAs($this->manager, ['*']);
    $this->postJson('/api/sales', priceCheckPayload(['discount_amount' => 10], price: 100))->assertCreated();

    $sale = DB::table('sales')->orderByDesc('id')->first();
    expect($sale->discount_approved_by)->toBeNull();
});

it('records who approved a discount above the limit', function () {
    Sanctum::actingAs($this->cashier, ['*']);

    $this->postJson('/api/sales', priceCheckPayload([
        'discount_amount' => 10,
        'discount_approved_by' => $this->manager->uuid,
        'override_approved_at' => now()->toIso8601String(),
    ]))->assertCreated();

    expect(DB::table('sales')->first()->discount_approved_by)->toBe($this->manager->id);
});

it('records a till\'s push below the expected price as charged, with an open review issue unless the approver held the permission', function () {
    $recorded = fn (array $items, array $extra = []) => app(RecordSale::class)->execute([
        'source_id' => $this->source,
        'delivery_location' => 'In store',
        'payment_method' => 'cash',
        'items' => $items,
        ...$extra,
    ], new SaleContext(
        clientUuid: (string) Str::uuid(),
        shopId: $this->shop->id,
        soldBy: $this->cashier->id,
    ));

    // No approver named: the sale is recorded as charged, and the line is open on the review.
    $open = $recorded([['product_id' => $this->soda->id, 'quantity' => 1, 'price' => 90]]);
    $issue = SyncIssue::query()->where('type', SyncIssue::PRICE_MISMATCH)->where('key', 'like', 'price:'.$open->sale->uuid.':%')->first();
    expect((float) $open->sale->items()->first()->unit_price)->toBe(90.0)
        ->and($issue)->not->toBeNull()
        ->and($issue->resolved_at)->toBeNull();

    // A manager named who holds the permission: already resolved as an authorised override.
    $approved = $recorded([['product_id' => $this->soda->id, 'quantity' => 1, 'price' => 90, 'price_overridden_by' => $this->manager->uuid, 'override_approved_at' => now()->toIso8601String()]]);
    $resolved = SyncIssue::query()->where('type', SyncIssue::PRICE_MISMATCH)->where('key', 'like', 'price:'.$approved->sale->uuid.':%')->first();
    expect($resolved->resolved_at)->not->toBeNull()
        ->and($resolved->resolution)->toBe('authorised override');

    // A named approver who doesn't hold it: open.
    $denied = $recorded([['product_id' => $this->soda->id, 'quantity' => 1, 'price' => 90, 'price_overridden_by' => $this->cashier->uuid, 'override_approved_at' => now()->toIso8601String()]]);
    $stillOpen = SyncIssue::query()->where('type', SyncIssue::PRICE_MISMATCH)->where('key', 'like', 'price:'.$denied->sale->uuid.':%')->first();
    expect($stillOpen->resolved_at)->toBeNull();
});

it('reviews a push discount above the seller\'s limit as a discount mismatch', function () {
    $recorded = app(RecordSale::class)->execute([
        'source_id' => $this->source,
        'delivery_location' => 'In store',
        'payment_method' => 'cash',
        'discount_amount' => 10,
        'items' => [['product_id' => $this->soda->id, 'quantity' => 1, 'price' => 100]],
    ], new SaleContext(clientUuid: (string) Str::uuid(), shopId: $this->shop->id, soldBy: $this->cashier->id));

    $issue = SyncIssue::query()->where('type', SyncIssue::PRICE_MISMATCH)->where('key', 'discount:'.$recorded->sale->uuid)->first();

    expect($issue)->not->toBeNull()
        ->and($issue->details['kind'])->toBe('discount')
        ->and($issue->resolved_at)->toBeNull();
});

it('shows a web sale below the expected price against its line, and the quote gives the expected price', function () {
    $this->actingAs($this->cashier);

    $quote = $this->postJson(route('pos.quote'), ['items' => [['product_id' => $this->soda->id, 'quantity' => 2]]])
        ->assertOk()
        ->json('lines');
    expect((float) $quote[0]['unit_price'])->toBe(100.0)
        ->and($quote[0]['source'])->toBe('unit');

    $this->post(route('sales.store'), [
        'source_id' => $this->source,
        'delivery_location' => 'In store',
        'payment_method' => 'cash',
        'walk_in_customer_name' => 'Walk In',
        'walk_in_customer_phone' => '0700000000',
        'items' => [['product_id' => $this->soda->id, 'quantity' => 1, 'price' => 90]],
    ])->assertSessionHasErrors(['items.0.price']);

    expect(DB::table('sales')->count())->toBe(0);
});
