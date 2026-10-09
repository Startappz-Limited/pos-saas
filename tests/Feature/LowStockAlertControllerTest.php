<?php

use App\Enums\AlertStatus;
use App\Models\LowStockAlert;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * These routes were registered against controller methods that did not exist, so
 * every one of them threw at runtime. The tests below pin the behaviour of the
 * completed actions, including the acknowledge status transition that was
 * previously left half-applied.
 */
beforeEach(function () {
    foreach (['alerts.view', 'alerts.create', 'alerts.update'] as $name) {
        Permission::findOrCreate($name);
    }

    $this->shop = Shop::factory()->create();

    // `users` has no shop_id column — User::getShopIdAttribute() derives it from
    // the shop_user pivot, so shop assignment must go through the relation.
    $this->user = User::factory()->create();
    $this->user->shops()->sync([$this->shop->id]);
    $this->user->givePermissionTo(['alerts.view', 'alerts.create', 'alerts.update']);

    $this->actingAs($this->user);
});

function lowStockAlert(array $attributes = []): LowStockAlert
{
    return LowStockAlert::factory()->create(array_merge([
        'shop_id' => test()->shop->id,
    ], $attributes));
}

// --- acknowledge: the status-transition bug ---

it('moves an alert to acknowledged status, not just stamping the timestamp', function () {
    $alert = lowStockAlert(['status' => AlertStatus::PENDING]);

    $this->post(route('low-stock-alerts.acknowledge', $alert))
        ->assertRedirect();

    $alert->refresh();

    // Regression: previously only acknowledged_at/_by were written, so status
    // stayed PENDING and the alert never left the pending list.
    expect($alert->status)->toBe(AlertStatus::ACKNOWLEDGED)
        ->and($alert->acknowledged_at)->not->toBeNull()
        ->and($alert->acknowledged_by)->toBe($this->user->id)
        ->and($alert->isAcknowledged())->toBeTrue();
});

it('refuses to acknowledge an alert twice', function () {
    $alert = lowStockAlert(['status' => AlertStatus::ACKNOWLEDGED, 'acknowledged_at' => now()]);

    // The policy's acknowledge ability denies an already-acknowledged alert.
    $this->post(route('low-stock-alerts.acknowledge', $alert))
        ->assertForbidden();
});

// --- resolve / ignore ---

it('resolves a pending alert', function () {
    $alert = lowStockAlert(['status' => AlertStatus::PENDING]);

    $this->post(route('low-stock-alerts.resolve', $alert))->assertRedirect();

    expect($alert->refresh()->status)->toBe(AlertStatus::RESOLVED);
});

it('does not resolve an already resolved alert twice', function () {
    $alert = lowStockAlert(['status' => AlertStatus::RESOLVED]);

    $this->post(route('low-stock-alerts.resolve', $alert))
        ->assertRedirect()
        ->assertSessionHas('error');
});

it('ignores a pending alert', function () {
    $alert = lowStockAlert(['status' => AlertStatus::PENDING]);

    $this->post(route('low-stock-alerts.ignore', $alert))->assertRedirect();

    expect($alert->refresh()->status)->toBe(AlertStatus::IGNORED);
});

// --- listings ---

it('lists only pending alerts on the pending endpoint', function () {
    $pending = lowStockAlert(['status' => AlertStatus::PENDING]);
    $resolved = lowStockAlert(['status' => AlertStatus::RESOLVED]);

    $response = $this->get(route('low-stock-alerts.pending'))->assertOk();

    $ids = $response->viewData('alerts')->pluck('id');

    expect($ids)->toContain($pending->id)
        ->and($ids)->not->toContain($resolved->id);
});

it('excludes resolved and ignored alerts from the active endpoint', function () {
    $pending = lowStockAlert(['status' => AlertStatus::PENDING]);
    $acknowledged = lowStockAlert(['status' => AlertStatus::ACKNOWLEDGED, 'acknowledged_at' => now()]);
    $resolved = lowStockAlert(['status' => AlertStatus::RESOLVED]);
    $ignored = lowStockAlert(['status' => AlertStatus::IGNORED]);

    $ids = $this->get(route('low-stock-alerts.active'))->assertOk()->viewData('alerts')->pluck('id');

    expect($ids)->toContain($pending->id)
        ->and($ids)->toContain($acknowledged->id)
        ->and($ids)->not->toContain($resolved->id)
        ->and($ids)->not->toContain($ignored->id);
});

it('supplies the statistics payload the index view expects', function () {
    lowStockAlert(['status' => AlertStatus::PENDING]);

    $stats = $this->get(route('low-stock-alerts.pending'))->assertOk()->viewData('statistics');

    expect($stats)->toHaveKeys(['total_alerts', 'active_alerts', 'acknowledged_alerts']);
});

// --- check levels ---

it('raises alerts for products below their reorder level', function () {
    Product::factory()->create([
        'shop_id' => $this->shop->id,
        'track_stock' => true,
        'stock_quantity' => 1,
        'reorder_level' => 10,
    ]);

    $this->post(route('low-stock-alerts.check'))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(LowStockAlert::where('shop_id', $this->shop->id)->exists())->toBeTrue();
});

// --- authorization ---

it('denies a user without the alerts.view permission', function () {
    $outsider = User::factory()->create();
    $outsider->shops()->sync([$this->shop->id]);

    $this->actingAs($outsider)
        ->get(route('low-stock-alerts.pending'))
        ->assertForbidden();
});

it('denies resolving without the alerts.update permission', function () {
    $alert = lowStockAlert(['status' => AlertStatus::PENDING]);

    $viewer = User::factory()->create();
    $viewer->shops()->sync([$this->shop->id]);
    $viewer->givePermissionTo('alerts.view');

    $this->actingAs($viewer)
        ->post(route('low-stock-alerts.resolve', $alert))
        ->assertForbidden();
});
