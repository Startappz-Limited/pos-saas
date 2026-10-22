<?php

use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Platform\Impersonation;
use App\Support\Platform\PlatformAudit;
use App\Support\Tenancy\TenantContext;
use Tests\Support\PlatformRoutes;
use Tests\Support\TwoBusinesses;

/**
 * The platform console's door, Phase 5 (B5): only platform operators reach
 * `/platform` (everyone else, a business owner included, gets a 404), not
 * while impersonating; every console action is in the audit log with the
 * operator, the business and the action. Every `platform.*` route in the
 * route list is checked, so a new console route is covered on arrival.
 */
beforeEach(function () {
    migrateAuditDatabase();
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->operator = platformOperator();
    app(TenantContext::class)->forget();
});

it('has console routes to check', function () {
    expect(PlatformRoutes::all($this->fixture))->not->toBeEmpty();
});

it('answers 404 on every console route to a business owner, a member and someone in two businesses', function () {
    foreach ([$this->fixture->a->owner, $this->fixture->b->cashier, $this->fixture->both] as $user) {
        foreach (PlatformRoutes::all($this->fixture) as [$method, $url, $name]) {
            app(TenantContext::class)->forget();
            $status = $this->actingAs($user)->{$method}($url)->getStatusCode();
            expect($status)->toBe(404, "{$name} answered {$status} to {$user->name}");
        }
    }
});

it('sends a guest to sign in from every console route', function () {
    foreach (PlatformRoutes::all($this->fixture) as [$method, $url, $name]) {
        $this->{$method}($url)->assertRedirect(route('login'));
    }
});

it('opens the console to a platform operator and records the visit', function () {
    $this->actingAs($this->operator)->get(route('platform.dashboard'))->assertOk()->assertSeeText('Platform console');

    $audit = AuditLog::query()->where('user_id', $this->operator->id)->where('auditable_type', 'platform')->latest('id')->first();
    expect($audit->tags)->toBe(['platform', 'dashboard.viewed'])
        ->and($audit->user_id)->toBe($this->operator->id)
        ->and($audit->new_values['action'])->toBe('dashboard.viewed')
        ->and($audit->business_id)->toBeNull();
});

it('keeps the console closed while the operator impersonates someone', function () {
    $this->actingAs($this->operator)->withSession([Impersonation::OPERATOR_KEY => $this->operator->id, Impersonation::EXPIRES_KEY => now()->addMinutes(10)->getTimestamp()])
        ->get(route('platform.dashboard'))->assertNotFound();
});

it('sends a platform operator to the console at sign-in and from business pages', function () {
    $this->post(route('login'), ['email' => $this->operator->email, 'password' => 'Operat0r!Strong-pass'])
        ->assertRedirect(route('platform.dashboard', absolute: false));

    $this->actingAs($this->operator)->get(route('dashboard'))->assertRedirect(route('platform.dashboard'));
});

it('records a console action about a business on that business, with the operator', function () {
    $this->actingAs($this->operator);
    PlatformAudit::record('business.viewed', $this->fixture->b->business, ['section' => 'members']);

    $audit = AuditLog::query()->latest('id')->first();
    expect($audit->business_id)->toBe($this->fixture->b->business->id)
        ->and($audit->auditable_uuid)->toBe($this->fixture->b->business->uuid)
        ->and($audit->user_email)->toBe($this->operator->email)
        ->and($audit->new_values)->toBe(['action' => 'business.viewed', 'section' => 'members']);
});

it('marks what is done while impersonating with the operator who really acted', function () {
    $this->actingAs($this->fixture->a->cashier);
    session()->put(Impersonation::OPERATOR_KEY, $this->operator->id);

    AuditableEvent::dispatch(auditableType: User::class, auditableId: 1, auditableUuid: null, event: AuditEvent::UPDATED, userId: $this->fixture->a->cashier->id);

    expect(AuditLog::query()->latest('id')->first()->tags)->toBe(['impersonated_by:'.$this->operator->id]);
});

it('records every console page an operator opens, cross-business reads included', function () {
    foreach (PlatformRoutes::all($this->fixture) as [$method, $url, $name]) {
        if ($method !== 'get') {
            continue; // each change is checked for its audit row in its own test
        }

        $before = AuditLog::query()->where('user_id', $this->operator->id)->whereNotNull('tags')->count();
        $this->actingAs($this->operator)->get($url)->assertOk();

        $row = AuditLog::query()->where('user_id', $this->operator->id)->latest('id')->first();
        expect(AuditLog::query()->where('user_id', $this->operator->id)->whereNotNull('tags')->count())->toBeGreaterThan($before, "{$name} wrote no audit row")
            ->and($row->tags)->toContain('platform');
    }
});

it('carries the honeypot on the console forms and refuses a request that fills it in', function () {
    $b = $this->fixture->b->business;
    $this->actingAs($this->operator)->get(route('platform.businesses.show', $b))->assertOk()->assertSee('name="valid_from"', false);

    $this->actingAs($this->operator)->post(route('platform.businesses.suspend', $b), ['reason' => 'x', 'confirm' => '1', 'my_name' => 'I am a bot']);

    expect($b->fresh()->status)->toBe('active');
});
