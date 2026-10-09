<?php

use App\Enums\AlertCategory;
use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Calendar events are scheduled reminders on e-commerce orders and abandoned carts. They name
 * customers and follow-ups, so a user tied to one shop must never see another shop's. Shop-less
 * alerts belong to no business, so only a super-admin sees them (ShopAccessScope).
 */
function calendarReminder(?Shop $shop, string $title): Alert
{
    return Alert::create([
        'shop_id' => $shop?->id,
        'title' => $title,
        'message' => '',
        'type' => AlertType::ORDER_REMINDER,
        'severity' => AlertSeverity::MEDIUM,
        'category' => AlertCategory::ORDERS,
        'scheduled_at' => now()->addDay(),
    ]);
}

beforeEach(function () {
    $this->shopA = Shop::factory()->create();
    $this->shopB = Shop::factory()->create();

    calendarReminder($this->shopA, 'Call back shop A customer');
    calendarReminder($this->shopB, 'Call back shop B customer');
    calendarReminder(null, 'System-wide reminder');

    $this->staffA = User::factory()->create();
    $this->staffA->shops()->attach($this->shopA);
});

function calendarTitles($response): array
{
    $json = $response->json();

    return collect($json['data'] ?? $json)->pluck('title')->sort()->values()->all();
}

it('shows a shop-restricted user only their own shop on the web calendar', function () {
    $response = $this->actingAs($this->staffA)->getJson(route('calendar.events'))->assertOk();

    expect(calendarTitles($response))->toBe(['Call back shop A customer']);
});

it('shows a shop-restricted user only their own shop on the API calendar', function () {
    Sanctum::actingAs($this->staffA);

    $response = $this->getJson(route('api.calendar.events'))->assertOk()->assertJsonPath('success', true);

    expect(calendarTitles($response))->toBe(['Call back shop A customer']);
});

it('shows a user of several shops all of those shops and no others', function () {
    $shopC = Shop::factory()->create();
    calendarReminder($shopC, 'Call back shop C customer');
    $this->staffA->shops()->attach($shopC);

    $response = $this->actingAs($this->staffA)->getJson(route('calendar.events'))->assertOk();

    expect(calendarTitles($response))->toBe(['Call back shop A customer', 'Call back shop C customer']);
});

it('shows a shop owner every shop of their business', function () {
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->getJson(route('calendar.events'))->assertOk();

    expect(calendarTitles($response))->toBe(['Call back shop A customer', 'Call back shop B customer']);
});

it('shows everything, shop-less alerts included, to a super-admin', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::findOrCreate(Role::SUPER_ADMIN, 'web'));

    $response = $this->actingAs($superAdmin)->getJson(route('calendar.events'))->assertOk();

    expect(calendarTitles($response))->toBe(['Call back shop A customer', 'Call back shop B customer', 'System-wide reminder']);
});
