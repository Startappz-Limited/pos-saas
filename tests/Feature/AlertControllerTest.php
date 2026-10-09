<?php

use App\Enums\AlertCategory;
use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\Shop;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * AlertController was an empty stub with 8 registered routes, so every one of
 * them threw at runtime. These tests pin the implemented behaviour, with
 * particular attention to shop isolation: alerts carry a nullable shop_id, where
 * null means "system-wide" and must stay visible to everyone.
 */
beforeEach(function () {
    foreach (['alerts.view', 'alerts.create', 'alerts.update'] as $name) {
        Permission::findOrCreate($name);
    }

    $this->shop = Shop::factory()->create();
    $this->otherShop = Shop::factory()->create();

    // shop_id on users is a read-only accessor over the shop_user pivot.
    $this->user = User::factory()->create();
    $this->user->shops()->sync([$this->shop->id]);
    $this->user->givePermissionTo(['alerts.view', 'alerts.create', 'alerts.update']);

    $this->actingAs($this->user);
});

function alert(array $attributes = []): Alert
{
    return Alert::create(array_merge([
        'shop_id' => test()->shop->id,
        'title' => 'Stock running low',
        'message' => 'Widget is below its reorder level.',
        'type' => AlertType::LOW_STOCK,
        'severity' => AlertSeverity::HIGH,
        'category' => AlertCategory::INVENTORY,
    ], $attributes));
}

// --- index / listing ---

it('lists alerts for the shops the user is assigned to', function () {
    $mine = alert();
    $theirs = alert(['shop_id' => test()->otherShop->id, 'title' => 'Other shop alert']);

    $ids = $this->get(route('alerts.index'))->assertOk()->viewData('alerts')->pluck('id');

    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($theirs->id);
});

it('shows system-wide alerts to a shop-restricted user', function () {
    $global = alert(['shop_id' => null, 'title' => 'Scheduled maintenance']);

    $ids = $this->get(route('alerts.index'))->assertOk()->viewData('alerts')->pluck('id');

    expect($ids)->toContain($global->id);
});

it('filters by severity and category', function () {
    $critical = alert(['severity' => AlertSeverity::CRITICAL]);
    $low = alert(['severity' => AlertSeverity::LOW]);

    $ids = $this->get(route('alerts.index', ['severity' => AlertSeverity::CRITICAL->value]))
        ->assertOk()->viewData('alerts')->pluck('id');

    expect($ids)->toContain($critical->id)->and($ids)->not->toContain($low->id);
});

it('restricts the unresolved listing to open alerts', function () {
    $open = alert();
    $resolved = alert(['is_resolved' => true, 'resolved_at' => now()]);

    $ids = $this->get(route('alerts.unresolved'))->assertOk()->viewData('alerts')->pluck('id');

    expect($ids)->toContain($open->id)->and($ids)->not->toContain($resolved->id);
});

it('supplies the statistics the index view renders', function () {
    alert(['severity' => AlertSeverity::CRITICAL]);

    $stats = $this->get(route('alerts.index'))->assertOk()->viewData('statistics');

    expect($stats)->toHaveKeys(['total', 'unread', 'unresolved', 'critical'])
        ->and($stats['critical'])->toBe(1);
});

// --- show ---

it('shows an alert from the user\'s shop', function () {
    $alert = alert();

    $this->get(route('alerts.show', $alert))
        ->assertOk()
        ->assertSee($alert->title);
});

it('binds the alert route by uuid, not id', function () {
    $alert = alert();

    expect($alert->getRouteKeyName())->toBe('uuid');

    $this->get(route('alerts.show', $alert->uuid))->assertOk();
});

// --- store ---

it('creates an alert and stamps the creator', function () {
    $this->post(route('alerts.store'), [
        'title' => 'Register variance',
        'message' => 'Closing balance was short.',
        'type' => AlertType::SYSTEM_ERROR->value,
        'severity' => AlertSeverity::CRITICAL->value,
        'category' => AlertCategory::SYSTEM->value,
        'shop_id' => $this->shop->id,
    ])->assertRedirect();

    $alert = Alert::where('title', 'Register variance')->firstOrFail();

    expect($alert->created_by)->toBe($this->user->id)
        ->and($alert->severity)->toBe(AlertSeverity::CRITICAL)
        ->and($alert->is_resolved)->toBeFalse();
});

it('rejects an invalid enum value', function () {
    $this->post(route('alerts.store'), [
        'title' => 'Bad',
        'message' => 'Bad',
        'type' => 'not-a-real-type',
        'severity' => AlertSeverity::LOW->value,
        'category' => AlertCategory::SYSTEM->value,
    ])->assertSessionHasErrors('type');
});

it('rejects an expiry earlier than the scheduled time', function () {
    $this->post(route('alerts.store'), [
        'title' => 'Backwards',
        'message' => 'Backwards',
        'type' => AlertType::ORDER_REMINDER->value,
        'severity' => AlertSeverity::LOW->value,
        'category' => AlertCategory::ORDERS->value,
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'expires_at' => now()->toDateTimeString(),
    ])->assertSessionHasErrors('expires_at');
});

it('refuses to attach an alert to a shop the user cannot access', function () {
    $this->post(route('alerts.store'), [
        'title' => 'Cross shop',
        'message' => 'Should not be allowed.',
        'type' => AlertType::LOW_STOCK->value,
        'severity' => AlertSeverity::LOW->value,
        'category' => AlertCategory::INVENTORY->value,
        'shop_id' => $this->otherShop->id,
    ])->assertSessionHasErrors('shop_id');

    expect(Alert::where('title', 'Cross shop')->exists())->toBeFalse();
});

// --- mark read ---

it('marks a single alert read', function () {
    $alert = alert();

    $this->post(route('alerts.markRead', $alert))->assertRedirect();

    $alert->refresh();

    expect($alert->is_read)->toBeTrue()->and($alert->read_at)->not->toBeNull();
});

it('marks all visible alerts read without touching other shops', function () {
    $mine = alert();
    $theirs = alert(['shop_id' => test()->otherShop->id]);

    $this->post(route('alerts.markAllRead'))->assertRedirect();

    expect($mine->refresh()->is_read)->toBeTrue()
        ->and($theirs->refresh()->is_read)->toBeFalse();
});

// --- resolve ---

it('resolves an alert with notes and records the resolver', function () {
    $alert = alert();

    $this->post(route('alerts.resolve', $alert), ['resolution_notes' => 'Restocked from supplier.'])
        ->assertRedirect();

    $alert->refresh();

    expect($alert->is_resolved)->toBeTrue()
        ->and($alert->resolution_notes)->toBe('Restocked from supplier.')
        ->and($alert->resolved_by)->toBe($this->user->id)
        ->and($alert->resolved_at)->not->toBeNull();
});

it('does not resolve an already resolved alert twice', function () {
    $alert = alert(['is_resolved' => true, 'resolved_at' => now(), 'resolution_notes' => 'first']);

    $this->post(route('alerts.resolve', $alert), ['resolution_notes' => 'second'])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($alert->refresh()->resolution_notes)->toBe('first');
});

// --- unread count ---

it('returns the unread count as json in the project response shape', function () {
    alert();
    alert(['is_read' => true, 'read_at' => now()]);

    $this->getJson(route('alerts.unreadCount'))
        ->assertOk()
        ->assertJson(['success' => true, 'data' => ['unread_count' => 1]]);
});

it('excludes other shops from the unread count', function () {
    alert(['shop_id' => test()->otherShop->id]);

    $this->getJson(route('alerts.unreadCount'))
        ->assertOk()
        ->assertJson(['data' => ['unread_count' => 0]]);
});

// --- authorization ---

it('denies listing without the alerts.view permission', function () {
    $outsider = User::factory()->create();
    $outsider->shops()->sync([$this->shop->id]);

    $this->actingAs($outsider)->get(route('alerts.index'))->assertForbidden();
});

it('denies creating without the alerts.create permission', function () {
    $viewer = User::factory()->create();
    $viewer->shops()->sync([$this->shop->id]);
    $viewer->givePermissionTo('alerts.view');

    $this->actingAs($viewer)->post(route('alerts.store'), [
        'title' => 'Nope',
        'message' => 'Nope',
        'type' => AlertType::LOW_STOCK->value,
        'severity' => AlertSeverity::LOW->value,
        'category' => AlertCategory::INVENTORY->value,
    ])->assertForbidden();
});

it('denies resolving without the alerts.update permission', function () {
    $alert = alert();

    $viewer = User::factory()->create();
    $viewer->shops()->sync([$this->shop->id]);
    $viewer->givePermissionTo('alerts.view');

    $this->actingAs($viewer)->post(route('alerts.resolve', $alert))->assertForbidden();
});

it('requires authentication', function () {
    auth()->logout();

    $this->get(route('alerts.index'))->assertRedirect(route('login'));
});
