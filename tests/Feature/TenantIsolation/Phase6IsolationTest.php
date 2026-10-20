<?php

use App\Support\Tenancy\TenantContext;
use Tests\Support\TwoBusinesses;

/**
 * The rows of the isolation suite whose endpoints arrive in Phase 6 (`/api/v1`,
 * sync and M-Pesa). Written now so they're filled in as those endpoints are
 * built (Phase 2); until then they show as pending in every run.
 */
it('refuses an /api/v1 token for business A when it acts on business B', function () {
    seedRolesAndPermissions();
    $fixture = TwoBusinesses::create();
    $both = $fixture->both;
    foreach ([$fixture->a->business, $fixture->b->business] as $business) {
        app(TenantContext::class)->run($business, fn () => $both->givePermissionTo(['customers.view', 'customers.update']));
    }
    app(TenantContext::class)->forget();

    $token = $this->postJson('/api/v1/auth/sign-in', [
        'email' => $both->email, 'password' => 'password', 'device_name' => 'Till', 'business' => $fixture->a->business->uuid,
    ])->json('data.token');
    app('auth')->forgetGuards();
    app(TenantContext::class)->forget();
    $headers = ['Authorization' => "Bearer {$token}"];

    // Even for a user who is a member of B, an A token never reaches B's records.
    $this->withHeaders($headers)->getJson(route('api.customers.show', $fixture->b->customer))->assertNotFound();
    $this->withHeaders($headers)->putJson(route('api.customers.update', $fixture->b->customer), ['name' => 'Taken over'])->assertNotFound();
    $this->withHeaders($headers)->getJson('/api/customers')->assertOk()->assertDontSee('Bravo Customer');

    expect(app(TenantContext::class)->run($fixture->b->business, fn () => $fixture->b->customer->fresh()->name))->toBe('Bravo Customer');
});

it('refuses business B\'s records on every /api/v1 resource: index, show, update and delete')->todo();

it('never returns business B\'s rows to a sync pull for business A')->todo();

it('rejects a sync push for business A that carries business B\'s ids')->todo();

it('keeps an M-Pesa payment webhook within the business whose shortcode it names')->todo();
