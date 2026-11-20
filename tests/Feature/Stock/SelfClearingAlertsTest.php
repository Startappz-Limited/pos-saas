<?php

use App\Actions\CheckLowStockLevels;
use App\Actions\Stock\SyncExpiringStockAlerts;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\StockMovementType;
use App\Jobs\CheckShopLowStockJob;
use App\Models\Alert;
use App\Models\CostLayer;
use App\Models\LowStockAlert;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

/*
 * Stage 11c, step 2: alerts that clear themselves. A low-stock alert resolves when its item recovers and opens
 * again if it drops back; a system run names no user; a stock change that crosses a reorder level re-checks the
 * shop; and an expiring batch raises one alert, becomes an expired one, and clears when it has no stock left.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-11-03 10:00:00', 'Africa/Nairobi'));
    $this->ledger = app(StockLedger::class);
    $this->shop = Shop::factory()->create(['code' => 'NRB']);
    $this->user = User::factory()->create();
    $this->user->assignRole(ownerRole());

    $this->tablets = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'has_variations' => false, 'track_stock' => true, 'reorder_level' => 10]);
    $this->tablets->defaultVariation->baseUnit()->update(['precision' => 0]);
});

it('raises a low-stock alert with no user for a system run, and records who ran a manual one', function () {
    $this->ledger->receive($this->shop->id, $this->tablets->defaultVariation, 4, 5.0, StockMovementType::INTAKE);

    app(CheckLowStockLevels::class)->execute($this->shop->id, null);
    $system = LowStockAlert::query()->where('product_id', $this->tablets->id)->sole();

    expect($system->source)->toBe('system')
        ->and($system->created_by)->toBeNull();

    $system->resolve();
    app(CheckLowStockLevels::class)->execute($this->shop->id, $this->user->id);
    $manual = LowStockAlert::query()->where('product_id', $this->tablets->id)->where('status', AlertStatus::PENDING)->sole();

    expect($manual->source)->toBe('user')
        ->and($manual->created_by)->toBe($this->user->id);
});

it('resolves an open alert as "stock recovered" when the item is back above its reorder level, and reopens it when it drops again', function () {
    $this->ledger->receive($this->shop->id, $this->tablets->defaultVariation, 4, 5.0, StockMovementType::INTAKE);
    app(CheckLowStockLevels::class)->execute($this->shop->id, null);

    $this->ledger->receive($this->shop->id, $this->tablets->defaultVariation, 20, 5.0, StockMovementType::INTAKE);
    app(CheckLowStockLevels::class)->execute($this->shop->id, null);

    $alert = LowStockAlert::query()->where('product_id', $this->tablets->id)->sole();
    expect($alert->status)->toBe(AlertStatus::RESOLVED)
        ->and($alert->resolution)->toBe('stock_recovered')
        ->and($alert->resolved_at)->not->toBeNull()
        ->and($alert->updated_by)->toBeNull();

    // It drops back: a new alert opens (resolved ones are not reused).
    $this->ledger->consume($this->shop->id, $this->tablets->defaultVariation, 19, StockMovementType::SALE, $this->tablets->defaultVariation);
    app(CheckLowStockLevels::class)->execute($this->shop->id, null);

    expect(LowStockAlert::query()->where('product_id', $this->tablets->id)->where('status', AlertStatus::PENDING)->count())->toBe(1)
        ->and(LowStockAlert::query()->where('product_id', $this->tablets->id)->count())->toBe(2);
});

it('re-checks the shop when a stock change crosses a reorder level, and not when it does not', function () {
    // Stock of 20 against a reorder level of 10: above it, so nothing is queued while it arrives.
    $this->ledger->receive($this->shop->id, $this->tablets->defaultVariation, 20, 5.0, StockMovementType::INTAKE);

    Queue::fake();
    // 20 to 5: crosses below the level, so the shop is re-checked.
    $this->ledger->consume($this->shop->id, $this->tablets->defaultVariation, 15, StockMovementType::SALE, $this->tablets->defaultVariation);
    Queue::assertPushed(CheckShopLowStockJob::class, fn (CheckShopLowStockJob $job) => $job->shopId === $this->shop->id);

    // 5 to 7: still below the level, no crossing, nothing queued.
    Queue::fake();
    $this->ledger->receive($this->shop->id, $this->tablets->defaultVariation, 2, 5.0, StockMovementType::INTAKE);
    Queue::assertNotPushed(CheckShopLowStockJob::class);
});

it('raises one expiring-stock alert for a batch expiring in the window, once however often it is checked', function () {
    $this->ledger->receive($this->shop->id, $this->tablets->defaultVariation, 50, 5.0, StockMovementType::INTAKE, ['batch_number' => 'B1', 'expiry_date' => '2026-12-01']);
    $layer = CostLayer::query()->where('shop_id', $this->shop->id)->sole();

    app(SyncExpiringStockAlerts::class)->execute($this->shop->id);
    app(SyncExpiringStockAlerts::class)->execute($this->shop->id);

    $alert = Alert::query()->where('shop_id', $this->shop->id)->where('alertable_type', CostLayer::class)->sole();
    expect($alert->type)->toBe(AlertType::STOCK_EXPIRING)
        ->and($alert->alertable_id)->toBe($layer->id)
        ->and($alert->created_by)->toBeNull()
        ->and($alert->data['dedupe_key'])->toBe("expiring:{$this->shop->id}:{$layer->id}");
});

it('turns an expiring alert into an expired one once the batch has expired, and clears it when the batch is sold out', function () {
    $this->ledger->receive($this->shop->id, $this->tablets->defaultVariation, 50, 5.0, StockMovementType::INTAKE, ['batch_number' => 'B1', 'expiry_date' => '2026-11-10']);
    app(SyncExpiringStockAlerts::class)->execute($this->shop->id);

    $this->travelTo(Carbon::parse('2026-11-12 10:00:00', 'Africa/Nairobi'));
    app(SyncExpiringStockAlerts::class)->execute($this->shop->id);

    $alert = Alert::query()->where('shop_id', $this->shop->id)->sole();
    expect($alert->type)->toBe(AlertType::STOCK_EXPIRED)
        ->and($alert->is_resolved)->toBeFalse();

    // Written off (or sold): the batch has no stock, so the alert clears itself.
    $this->ledger->consume($this->shop->id, $this->tablets->defaultVariation, 50, StockMovementType::ADJUSTMENT_REMOVE, $this->tablets->defaultVariation);
    app(SyncExpiringStockAlerts::class)->execute($this->shop->id);

    expect($alert->fresh()->is_resolved)->toBeTrue();
});

it('does not alert a batch outside the window', function () {
    $this->ledger->receive($this->shop->id, $this->tablets->defaultVariation, 50, 5.0, StockMovementType::INTAKE, ['batch_number' => 'B2', 'expiry_date' => '2027-06-01']);

    app(SyncExpiringStockAlerts::class)->execute($this->shop->id);

    expect(Alert::query()->where('shop_id', $this->shop->id)->count())->toBe(0);
});
