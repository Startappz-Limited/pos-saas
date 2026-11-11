<?php

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\SyncIssue;
use App\Models\User;
use App\Notifications\Approvals\ApprovalRequested;
use App\Services\Stock\StockLedger;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/*
 * Overselling (decided 5 November 2026, stage 11 step 4 follow-up): the web
 * POS and the flat API refuse a sale beyond the shop's stock by default
 * (422 insufficient_stock, enforced inside StockLedger's row lock); a holder
 * of sales.oversell pushes it through with a reason, flagged on the till
 * sync review and the owner notified; the reason is ignored for anyone
 * without the permission. A till's push, an online order and an
 * abandoned-cart conversion are never refused for this (SalePathsStockTest,
 * SyncPushTest cover those paths unchanged). The negative_stock issue
 * self-resolves once the shop's stock for that variation returns to zero
 * or above.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->ledger = app(StockLedger::class);
    $this->shop = Shop::factory()->create();
    $this->owner = User::factory()->create();
    $this->owner->assignRole(ownerRole());
    $this->source = DB::table('sale_sources')->where('business_id', testBusiness()->id)->value('id');

    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'selling_price' => 100, 'stock_quantity' => 0]);
    $this->ledger->receive($this->shop->id, $this->product->defaultVariation, 5, 60, StockMovementType::INTAKE);
});

function oversellPayload(float $quantity, ?string $reason = null): array
{
    return [
        'shop_id' => test()->shop->id,
        'source_id' => test()->source,
        'delivery_location' => 'In store',
        'walk_in_customer_name' => 'Walk In',
        'walk_in_customer_phone' => '0700000000',
        'payment_method' => 'cash',
        'items' => [
            ['product_id' => test()->product->id, 'quantity' => $quantity, 'price' => 100],
        ],
        ...($reason !== null ? ['oversell_reason' => $reason] : []),
    ];
}

it('refuses a web POS sale beyond the shop\'s stock, with no sale and no stock change', function () {
    $this->actingAs($this->owner)->postJson(route('sales.store'), oversellPayload(8))
        ->assertStatus(422)->assertJsonPath('code', 'insufficient_stock');

    expect(Sale::count())->toBe(0)
        ->and($this->ledger->quantity($this->shop->id, $this->product->defaultVariation->id))->toBe(5);
});

it('pushes a web POS sale through with sales.oversell and a reason, flagged and the owner notified', function () {
    // The owner holds sales.oversell by default.
    $this->actingAs($this->owner)->postJson(route('sales.store'), oversellPayload(8, 'Regular customer, restocking tomorrow'))
        ->assertOk();

    $sale = Sale::latest('id')->firstOrFail();
    expect($this->ledger->quantity($this->shop->id, $this->product->defaultVariation->id))->toBe(-3);

    $issue = SyncIssue::query()->where('type', SyncIssue::NEGATIVE_STOCK)->sole();
    expect($issue->details['overridden_by'])->toBe($this->owner->name)
        ->and($issue->details['reason'])->toBe('Regular customer, restocking tomorrow')
        ->and($issue->shop_id)->toBe($this->shop->id)
        ->and($issue->resolved_at)->toBeNull()
        ->and($this->owner->notifications()->where('type', ApprovalRequested::class)->count())->toBe(1);
});

it('ignores a reason from someone without sales.oversell: still refused', function () {
    $clerk = User::factory()->create();
    $clerk->assignRole('cashier');
    $clerk->shops()->attach($this->shop);
    expect($clerk->can('sales.oversell'))->toBeFalse();

    $this->actingAs($clerk)->postJson(route('sales.store'), oversellPayload(8, 'I\'ll just say this works'))
        ->assertStatus(422)->assertJsonPath('code', 'insufficient_stock');

    expect(Sale::count())->toBe(0);
});

it('self-resolves the negative_stock issue once the shop\'s stock for that variation returns to zero or above', function () {
    $this->actingAs($this->owner)->postJson(route('sales.store'), oversellPayload(8, 'Restocking tomorrow'))->assertOk();
    $issue = SyncIssue::query()->where('type', SyncIssue::NEGATIVE_STOCK)->sole();
    expect($issue->resolved_at)->toBeNull();

    // Short of covering the shortfall: still open.
    $this->ledger->receive($this->shop->id, $this->product->defaultVariation, 2, 60, StockMovementType::INTAKE);
    expect($issue->fresh()->resolved_at)->toBeNull();

    // Covers it: self-resolves.
    $this->ledger->receive($this->shop->id, $this->product->defaultVariation, 5, 60, StockMovementType::INTAKE);
    expect($issue->fresh())
        ->resolved_at->not->toBeNull()
        ->resolution->toBe('self_resolved');
});

it('gives sales.oversell to owners and managers, not cashiers, after seeding and on rollout', function () {
    $manager = User::factory()->create();
    $manager->assignRole('manager');
    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');

    expect($this->owner->can('sales.oversell'))->toBeTrue()
        ->and($manager->can('sales.oversell'))->toBeTrue()
        ->and($cashier->can('sales.oversell'))->toBeFalse();

    // Rollout: an install from before this follow-up has neither the permission nor the grant.
    Permission::query()->where('name', 'sales.oversell')->delete();
    $template = Role::withoutGlobalScopes()->whereNull('business_id')->where('name', 'manager')->firstOrFail();
    expect($template->permissions()->pluck('name'))->not->toContain('sales.oversell');

    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    expect($template->permissions()->pluck('name'))->toContain('sales.oversell');
});

it('canBeSoldAt() compares against the quantity asked for, not just zero', function () {
    expect($this->product->canBeSoldAt($this->shop, quantity: '5'))->toBeTrue()
        ->and($this->product->canBeSoldAt($this->shop, quantity: '6'))->toBeFalse()
        ->and($this->product->canBeSoldAt($this->shop))->toBeTrue(); // default '1'
});
