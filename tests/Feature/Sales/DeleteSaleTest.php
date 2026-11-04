<?php

use App\Enums\StockMovementType;
use App\Models\CashRegister;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Stock\StockLedger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/*
 * Deleting a sale (stage 10, step 5): a completed sale is voided, never
 * deleted; a draft or pending sale with no payments can be deleted by the
 * owner, its stock released (SALE_DELETE) and the sale soft-deleted, keeping
 * its row, lines and invoice number; a pending sale with payments is voided.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->ledger = app(StockLedger::class);
    $this->shop = Shop::factory()->create();
    $this->member = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role === 'owner' ? ownerRole() : $role);
        $user->shops()->attach($this->shop);

        return $user;
    };
    $this->owner = ($this->member)('owner');
    $this->manager = ($this->member)('manager');
    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0]);
    $this->variation = $this->product->defaultVariation;
    $this->ledger->receive($this->shop->id, $this->variation, 10, 40, StockMovementType::INTAKE);
    $this->till = CashRegister::factory()->create(['shop_id' => $this->shop->id, 'user_id' => $this->owner->id]);
    $this->sell = function (array $attributes = []) {
        $sale = Sale::factory()->create([
            'shop_id' => $this->shop->id, 'register_id' => $this->till->id, 'status' => 'pending', 'completed_at' => null,
            'payment_method' => 'cash', 'total_amount' => 300, 'paid_amount' => 0, 'tax_amount' => 0, ...$attributes,
        ]);
        $line = SaleItem::create([
            'uuid' => (string) Str::uuid(), 'sale_id' => $sale->id, 'product_id' => $this->product->id, 'shop_id' => $this->shop->id,
            'quantity' => 3, 'unit_price' => 100, 'line_total' => 300, 'taxable_amount' => 300, 'status' => 'pending',
        ]);
        $this->ledger->sellItem($line, $this->variation);

        return $sale->fresh();
    };
    $this->stock = fn (): int => $this->ledger->quantity($this->shop->id, $this->variation->id);
});

it('refuses to delete a completed sale on the web and the flat API, and changes nothing', function () {
    $sale = ($this->sell)(['status' => 'completed', 'completed_at' => now(), 'paid_amount' => 300]);

    $this->actingAs($this->owner)->delete(route('sales.destroy', $sale))->assertSessionHas('error', "Completed sales can't be deleted. Void the sale instead.");
    Sanctum::actingAs($this->owner, ['*']);
    $this->deleteJson("/api/sales/{$sale->getRouteKey()}")->assertUnprocessable()->assertJsonPath('code', 'sale_completed');

    expect(Sale::query()->whereKey($sale->id)->exists())->toBeTrue()
        ->and(($this->stock)())->toBe(7);
});

it('deletes a pending sale with no payments: stock back (SALE_DELETE), soft-deleted, its number kept and never reissued', function () {
    $sale = ($this->sell)();
    $number = $sale->invoice_number;

    $this->actingAs($this->owner)->delete(route('sales.destroy', $sale))->assertRedirect(route('sales.index'))->assertSessionHas('success');

    $kept = Sale::withTrashed()->find($sale->id);
    expect(($this->stock)())->toBe(10)
        ->and(Sale::query()->whereKey($sale->id)->exists())->toBeFalse()
        ->and($kept->trashed())->toBeTrue()
        ->and($kept->invoice_number)->toBe($number)
        ->and(SaleItem::where('sale_id', $sale->id)->count())->toBe(1);

    $movement = StockMovement::where('movement_type', StockMovementType::SALE_DELETE->value)->sole();
    expect($movement->quantity)->toBe(3)
        ->and($movement->reference?->is($kept))->toBeTrue();

    // The number stays taken: a new sale can't reuse it.
    expect(fn () => Sale::factory()->create(['shop_id' => $this->shop->id, 'invoice_number' => $number]))->toThrow(QueryException::class);
});

it('leaves a deleted sale out of lists and reports; its page opens with a banner for sales.view-all and is a 404 for others', function () {
    $sale = ($this->sell)();
    $this->actingAs($this->owner)->delete(route('sales.destroy', $sale));

    $this->actingAs($this->owner)->get(route('sales.index'))->assertOk()->assertDontSee($sale->invoice_number);
    $this->actingAs($this->owner)->get(route('dashboard'))->assertOk();
    Sanctum::actingAs($this->owner, ['*']);
    expect(json_encode($this->getJson('/api/reports/pending-payments')->assertOk()->json()))->not->toContain($sale->uuid)
        ->and(json_encode($this->getJson('/api/sales')->assertOk()->json()))->not->toContain($sale->uuid);
    $this->getJson("/api/sales/{$sale->getRouteKey()}")->assertNotFound();

    $this->actingAs($this->owner)->get(route('sales.show', $sale))->assertOk()->assertSee('Deleted')->assertSee($sale->invoice_number);
    $cashier = ($this->member)('cashier');
    $this->actingAs($cashier)->get(route('sales.show', $sale))->assertNotFound();
});

it('refuses to delete a pending sale with a payment (422), which can be voided instead', function () {
    $sale = ($this->sell)(['paid_amount' => 100]);
    SalePayment::create(['business_id' => testBusiness()->id, 'uuid' => (string) Str::uuid(), 'sale_id' => $sale->id, 'payment_number' => 'PAY-1', 'amount' => 100, 'payment_method' => 'cash', 'paid_at' => now()]);

    Sanctum::actingAs($this->owner, ['*']);
    $this->deleteJson("/api/sales/{$sale->getRouteKey()}")->assertUnprocessable()
        ->assertJsonPath('code', 'sale_has_payments')
        ->assertJsonPath('message', 'This sale has payments. Void it instead.');
    $this->postJson("/api/sales/{$sale->getRouteKey()}/void", ['void_reason' => 'Customer cancelled'])->assertOk();

    expect($sale->fresh()->status)->toBe('voided')
        ->and(($this->stock)())->toBe(10);
});

it('lets only someone holding sales.delete delete a pending sale: a manager can\'t, the owner can', function () {
    $sale = ($this->sell)();

    $this->actingAs($this->manager)->delete(route('sales.destroy', $sale))->assertForbidden();
    Sanctum::actingAs($this->manager, ['*']);
    $this->deleteJson("/api/sales/{$sale->getRouteKey()}")->assertForbidden();
    expect(Sale::query()->whereKey($sale->id)->exists())->toBeTrue();

    Sanctum::actingAs($this->owner, ['*']);
    $this->deleteJson("/api/sales/{$sale->getRouteKey()}")->assertOk();
    expect(Sale::query()->whereKey($sale->id)->exists())->toBeFalse()
        ->and(DB::table('sales')->where('id', $sale->id)->value('deleted_at'))->not->toBeNull();
});
