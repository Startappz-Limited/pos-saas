<?php

use App\Actions\Business\AcceptBusinessInvite;
use App\Actions\Business\StartTrial;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessInvite;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\Support\TwoBusinesses;

/**
 * Viewing a shop follows access, whatever the viewer's role (stage 10a,
 * step 3); managing one still needs its permissions. The default shop
 * matters only on the Free Trial, where every member gets it regardless of
 * what an invite asks for; on any other tier the invite must name at least
 * one shop.
 */
beforeEach(function () {
    migrateAuditDatabase();
    ownerRole();
    sharedRole('cashier');
    sharedRole('manager');
});

function freeTrialPlan(): Plan
{
    return Plan::query()->where('key', 'free-trial')->firstOrFail();
}

function tier1Plan(): Plan
{
    return Plan::query()->where('key', 'tier-1')->firstOrFail();
}

it('lets a cashier view only their own assigned shop, on the web and the flat API; another shop 404s', function () {
    $business = testBusiness();
    $shopA = Shop::factory()->create(['name' => 'Shop A', 'manager_id' => null]);
    $shopB = Shop::factory()->create(['name' => 'Shop B', 'manager_id' => null]);
    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');
    $cashier->shops()->attach($shopA->id);

    $this->actingAs($cashier);
    $html = $this->get(route('shops.index'))->assertOk()->getContent();
    expect($html)->toContain('Shop A')->not->toContain('Shop B');

    $this->get(route('shops.show', $shopA))->assertOk();
    $this->get(route('shops.show', $shopB))->assertNotFound();

    Sanctum::actingAs($cashier, ['*']);
    $this->getJson(route('api.shops.show', $shopB))->assertNotFound();
});

it('404s another business\'s shop entirely', function () {
    $fixture = TwoBusinesses::create();

    $this->actingAs($fixture->a->cashier)->get(route('shops.show', $fixture->b->shops[0]))->assertNotFound();
});

it('shows "You haven\'t been given a shop yet." instead of an empty list', function () {
    $business = testBusiness();
    Shop::factory()->create(['manager_id' => null]);
    $member = User::factory()->create();
    $member->assignRole('cashier');

    $html = $this->actingAs($member)->get(route('shops.index'))->assertOk()->getContent();

    expect(str_contains($html, 'haven&#039;t been given a shop yet') || str_contains($html, "haven't been given a shop yet"))->toBeTrue();
});

it('refuses a cashier editing a shop or seeing its integrations and staff; a manager with shops.update can', function () {
    $shop = Shop::factory()->create(['manager_id' => null]);
    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');
    $cashier->shops()->attach($shop->id);

    $html = $this->actingAs($cashier)->get(route('shops.show', $shop))->assertOk()->getContent();
    expect($html)->not->toContain('No integrations configured')->not->toContain('Assigned Staff (');
    $this->get(route('shops.edit', $shop))->assertForbidden();

    $manager = User::factory()->create();
    $manager->assignRole('manager');
    sharedRole('manager')->givePermissionTo('shops.update');
    $manager->shops()->attach($shop->id);

    $html = $this->actingAs($manager)->get(route('shops.show', $shop))->assertOk()->getContent();
    expect($html)->toContain('E-commerce Integrations')->toContain('Assigned Staff');
});

it('gives the default shop to an invite accepted on the Free Trial, whatever shop_ids it holds', function () {
    $business = testBusiness();
    app(StartTrial::class)->execute($business, freeTrialPlan());
    $shop = Shop::factory()->create(['name' => 'Main', 'manager_id' => null]);
    $business->forceFill(['default_shop_id' => $shop->id])->save();

    $owner = User::factory()->create();
    $owner->assignRole('admin');

    // The Free Trial allows one shop, so there is nothing else to request —
    // the invite is sent with no shop at all, and still gets the default.
    $this->actingAs($owner)->post(route('invites.store'), [
        'email' => 'joiner@bravo-shop.co.ke',
        'role_id' => Role::findByName('cashier')->id,
    ])->assertSessionHasNoErrors();

    $invite = BusinessInvite::query()->where('email', 'joiner@bravo-shop.co.ke')->first();
    // Not auto-joined by the factory (which would itself use a staff seat):
    // accepting the invite is what's under test.
    $joiner = app(TenantContext::class)->withoutBusiness(fn () => User::factory()->create(['email' => 'joiner@bravo-shop.co.ke']));
    app(AcceptBusinessInvite::class)->execute($invite, $joiner);

    expect($joiner->shops()->pluck('shops.id')->all())->toBe([$shop->id]);
});

it('refuses a non-Free-Trial invite with no shop, and gives exactly the shops chosen', function () {
    $business = testBusiness();
    app(StartTrial::class)->execute($business, tier1Plan(), 2);
    $shopA = Shop::factory()->create(['manager_id' => null]);
    $shopB = Shop::factory()->create(['manager_id' => null]);

    $owner = User::factory()->create();
    $owner->assignRole('admin');
    $this->actingAs($owner);

    $this->post(route('invites.store'), [
        'email' => 'nochoice@bravo-shop.co.ke', 'role_id' => Role::findByName('cashier')->id,
    ])->assertSessionHasErrors('shop_ids');

    $this->post(route('invites.store'), [
        'email' => 'chose@bravo-shop.co.ke', 'role_id' => Role::findByName('cashier')->id, 'shop_ids' => [$shopA->id],
    ])->assertSessionHasNoErrors();

    $invite = BusinessInvite::query()->where('email', 'chose@bravo-shop.co.ke')->first();
    $joiner = app(TenantContext::class)->withoutBusiness(fn () => User::factory()->create(['email' => 'chose@bravo-shop.co.ke']));
    app(AcceptBusinessInvite::class)->execute($invite, $joiner);

    expect($joiner->shops()->pluck('shops.id')->all())->toBe([$shopA->id]);
});

it('needs no shop for an owner invite, on the Free Trial or not', function () {
    $business = testBusiness();
    app(StartTrial::class)->execute($business, tier1Plan());
    Shop::factory()->create(['manager_id' => null]);

    $owner = User::factory()->create();
    $owner->assignRole('admin');

    $this->actingAs($owner)->post(route('invites.store'), [
        'email' => 'co-owner@bravo-shop.co.ke', 'role_id' => Role::findByName('admin')->id,
    ])->assertSessionHasNoErrors();
});

it('can\'t delete or deactivate the sole default shop on the Free Trial', function () {
    $business = testBusiness();
    app(StartTrial::class)->execute($business, freeTrialPlan());
    $shop = Shop::factory()->create(['manager_id' => null]);
    $business->forceFill(['default_shop_id' => $shop->id])->save();

    $owner = User::factory()->create();
    $owner->assignRole('admin');
    $this->actingAs($owner);

    $this->delete(route('shops.destroy', $shop))->assertSessionHas('error');
    $this->assertDatabaseHas('shops', ['id' => $shop->id, 'deleted_at' => null]);

    $this->post(route('shops.deactivate', $shop))->assertSessionHas('error');
    $this->assertDatabaseHas('shops', ['id' => $shop->id, 'status' => 'active']);
});

it('can delete or deactivate the default shop on a tier, and changing the default shop is audited', function () {
    $business = testBusiness();
    app(StartTrial::class)->execute($business, tier1Plan(), 2);
    $shop = Shop::factory()->create(['manager_id' => null]);
    $other = Shop::factory()->create(['manager_id' => null]);
    $business->forceFill(['default_shop_id' => $shop->id])->save();

    $owner = User::factory()->create();
    $owner->assignRole('admin');
    $this->actingAs($owner);

    $this->put(route('business.settings.update'), [
        'name' => $business->name, 'currency' => $business->currency, 'timezone' => $business->timezone,
        'default_shop_id' => $other->id,
    ])->assertSessionHasNoErrors();

    expect(AuditLog::query()->where('auditable_type', Business::class)->where('auditable_id', $business->id)
        ->whereJsonContains('tags', 'default_shop_id')->exists())->toBeTrue();

    $this->delete(route('shops.destroy', $shop))->assertRedirect(route('shops.index'));
});

it('audits a default shop set by billing code with no one signed in as System', function () {
    $business = testBusiness();
    Shop::factory()->create(['status' => 'active', 'manager_id' => null]);
    app('auth')->forgetGuards();

    Artisan::call('businesses:set-default-shop', ['--business' => $business->id, '--apply' => true]);

    $row = AuditLog::query()->where('auditable_type', Business::class)->where('auditable_id', $business->id)
        ->whereJsonContains('tags', 'default_shop_id')->first();

    expect($row)->not->toBeNull()->and($row->user_id)->toBeNull();
    expect($business->fresh()->default_shop_id)->not->toBeNull();
});

it('shows the "No shop yet" link only to someone who may update that member', function () {
    $business = testBusiness();
    $owner = User::factory()->create();
    $owner->assignRole('admin');
    $shopless = User::factory()->create();
    $shopless->assignRole('cashier');

    $html = $this->actingAs($owner)->get(route('users.index'))->assertOk()->getContent();
    expect($html)->toContain('No shop yet')->toContain(route('users.edit', $shopless));

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('users.view');
    $html = $this->actingAs($viewer)->get(route('users.index'))->assertOk()->getContent();
    expect($html)->toContain('No shop yet')->not->toContain(route('users.edit', $shopless));
});

it('Business::onFreeTrial() is true on the free-trial plan and false on a tier\'s trial', function () {
    $a = testBusiness();
    app(StartTrial::class)->execute($a, freeTrialPlan());
    expect($a->onFreeTrial())->toBeTrue();

    $b = Business::factory()->create();
    app(TenantContext::class)->run($b, function () use ($b): void {
        app(StartTrial::class)->execute($b, tier1Plan());
    });
    expect($b->onFreeTrial())->toBeFalse();
});

it('users:grant-default-shop gives Free Trial members the default shop, is idempotent, and only lists others', function () {
    $business = testBusiness();
    app(StartTrial::class)->execute($business, freeTrialPlan());
    $shop = Shop::factory()->create(['manager_id' => null]);
    $business->forceFill(['default_shop_id' => $shop->id])->save();
    $member = User::factory()->create();
    $member->assignRole('cashier');

    Artisan::call('users:grant-default-shop', ['--business' => $business->id, '--apply' => true]);
    expect($member->shops()->pluck('shops.id')->all())->toBe([$shop->id]);

    Artisan::call('users:grant-default-shop', ['--business' => $business->id, '--apply' => true]);
    expect($member->shops()->pluck('shops.id')->all())->toBe([$shop->id]);

    $tierBusiness = Business::factory()->create();
    $tierMember = app(TenantContext::class)->run($tierBusiness, function () use ($tierBusiness) {
        app(StartTrial::class)->execute($tierBusiness, tier1Plan());
        ownerRole();
        sharedRole('cashier');
        $u = User::factory()->memberOf($tierBusiness)->create();
        $u->assignRole('cashier');

        return $u;
    });

    $output = Artisan::output();
    Artisan::call('users:grant-default-shop', ['--business' => $tierBusiness->id]);
    expect(Artisan::output())->toContain('listed');
    expect($tierMember->shops()->count())->toBe(0);
});
