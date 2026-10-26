<?php

use App\Actions\Sale\RecordSale;
use App\Models\Sale;
use App\Services\Stock\StockLedger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\Support\TwoBusinesses;

/**
 * RecordSale, Phase 7 (step 4): the flat API's sale, taken out of the
 * controller so sync push records sales by the same rules. Called directly
 * here, as the push will: a sale with its invoice number, lines and stock
 * taken, or nothing at all when it fails part way.
 */
beforeEach(function () {
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->tenant = app(TenantContext::class);
    $this->a = $this->fixture->a;
    $this->actingAs($this->a->owner);
    $this->tenant->set($this->a->business);
});

function recordSalePayload(array $items): array
{
    return [
        'source_id' => DB::table('sale_sources')->where('business_id', test()->a->business->id)->value('id'),
        'delivery_location' => 'In store',
        'walk_in_customer_name' => 'Walk-in',
        'walk_in_customer_phone' => '0700000000',
        'payment_method' => 'cash',
        'items' => $items,
    ];
}

it('records a paid sale with an invoice number, its lines and the stock taken', function () {
    $product = $this->a->product;
    $shopId = $product->shop_id;
    $variation = $product->defaultVariation()->firstOrFail();
    $before = app(StockLedger::class)->quantity($shopId, $variation->id);

    $recorded = app(RecordSale::class)->execute(recordSalePayload([
        ['product_id' => $product->id, 'quantity' => 2, 'price' => 150],
    ]));

    expect($recorded->sale->status)->toBe('completed')
        ->and($recorded->sale->payment_status)->toBe('paid')
        ->and($recorded->sale->invoice_number)->toStartWith('INV-')
        ->and((float) $recorded->sale->total_amount)->toBe(300.0)
        ->and($recorded->sale->items()->count())->toBe(1)
        ->and(app(StockLedger::class)->quantity($shopId, $variation->id))->toBe($before - 2)
        ->and($recorded->overCreditLimit)->toBeFalse();
});

it('makes nothing when the sale fails part way', function () {
    $sales = Sale::query()->count();
    $movements = DB::table('stock_movements')->count();

    expect(fn () => app(RecordSale::class)->execute(recordSalePayload([
        ['product_id' => $this->a->product->id, 'quantity' => 1, 'price' => 100],
        ['product_id' => 999999, 'quantity' => 1, 'price' => 100],
    ])))->toThrow(Exception::class);

    expect(Sale::query()->count())->toBe($sales)
        ->and(DB::table('stock_movements')->count())->toBe($movements);
});
