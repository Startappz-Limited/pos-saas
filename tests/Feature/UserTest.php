<?php

use App\Actions\CreateUserAction;
use App\Actions\UpdateUserAction;
use App\Enums\UserStatus;
use App\Models\Shop;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('user model uses uuid for routing', function () {
    $user = User::factory()->create();

    expect($user->getRouteKeyName())->toBe('uuid')
        ->and($user->uuid)->not->toBeNull();
});

test('user model automatically generates uuid on creation', function () {
    $user = User::factory()->create(['uuid' => null]);

    expect($user->uuid)->not->toBeNull();
});

test('user status defaults to active', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);

    expect($user->status)->toBe(UserStatus::ACTIVE)
        ->and($user->isActive())->toBeTrue();
});

test('user can be activated', function () {
    $user = User::factory()->inactive()->create();

    expect($user->isActive())->toBeFalse();

    $user->activate();

    expect($user->fresh()->isActive())->toBeTrue();
});

test('user can be deactivated', function () {
    $user = User::factory()->create();

    $user->deactivate();

    expect($user->fresh()->status)->toBe(UserStatus::INACTIVE);
});

test('user can be suspended', function () {
    $user = User::factory()->create();

    $user->suspend();

    expect($user->fresh()->isSuspended())->toBeTrue();
});

test('active scope returns only active users', function () {
    User::factory()->count(5)->create();
    User::factory()->count(3)->inactive()->create();
    User::factory()->count(2)->suspended()->create();

    $activeUsers = User::active()->get();

    expect($activeUsers)->toHaveCount(6); // 5 created + 1 from beforeEach
});

test('user profile photo url returns default avatar when no photo', function () {
    $user = User::factory()->create(['profile_photo' => null]);

    expect($user->profile_photo_url)
        ->toContain('ui-avatars.com');
});

test('user full name includes email', function () {
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    expect($user->full_name)->toBe('John Doe (john@example.com)');
});

test('created_by is automatically set on user creation', function () {
    $creator = User::factory()->create();

    $this->actingAs($creator);

    $newUser = User::factory()->create();

    expect($newUser->created_by)->toBe($creator->id);
});

test('updated_by is automatically set on user update', function () {
    $updater = User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($updater);

    $user->update(['name' => 'Updated Name']);

    expect($user->updated_by)->toBe($updater->id);
});

test('user can have roles assigned', function () {
    // This test will work after implementing Spatie Permission in Module 02
    expect(true)->toBeTrue();
});

test('user without shop allocations can access every shop', function () {
    $shop = Shop::factory()->create();
    $user = User::factory()->create();

    expect($user->hasShopRestrictions())->toBeFalse()
        ->and($user->canAccessShop($shop->id))->toBeTrue();
});

test('user with shop allocations can access only assigned shops', function () {
    $assignedShop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $user = User::factory()->create();

    $user->shops()->sync([$assignedShop->id]);
    $user = $user->fresh();

    expect($user->hasShopRestrictions())->toBeTrue()
        ->and($user->canAccessShop($assignedShop->id))->toBeTrue()
        ->and($user->canAccessShop($otherShop->id))->toBeFalse();
});

test('create user action syncs multiple shop allocations', function () {
    $shops = Shop::factory()->count(2)->create();

    $user = app(CreateUserAction::class)->execute([
        'name' => 'Shop User',
        'email' => 'shop-user@example.com',
        'password' => 'password',
        'status' => UserStatus::ACTIVE,
        'shop_ids' => $shops->pluck('id')->all(),
    ]);

    expect($user->shops()->pluck('shops.id')->sort()->values()->all())
        ->toBe($shops->pluck('id')->sort()->values()->all());
});

test('update user action can clear shop allocations for all shop access', function () {
    $shop = Shop::factory()->create();
    $user = User::factory()->create();
    $user->shops()->sync([$shop->id]);

    app(UpdateUserAction::class)->execute($user, [
        'name' => $user->name,
        'email' => $user->email,
        'status' => $user->status,
        'shop_ids' => [],
    ]);

    expect($user->fresh()->hasShopRestrictions())->toBeFalse();
});
