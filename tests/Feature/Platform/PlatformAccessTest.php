<?php

use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\Platform\Impersonation;
use App\Support\Platform\PlatformAudit;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;
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

/**
 * Every console route with a URL to call, its parameters filled from the
 * fixture (business B's records, so a leak would show).
 *
 * @return list<array{0: string, 1: string, 2: string}> [method, url, name]
 */
function platformRoutes(TwoBusinesses $fixture): array
{
    $values = [
        'business' => fn () => $fixture->b->business,
        'user' => fn () => $fixture->b->cashier,
        'member' => fn () => $fixture->b->cashier->uuid,
        'role' => fn () => sharedRole('manager'),
        'copy' => fn () => Role::withoutGlobalScopes()->where('business_id', $fixture->b->business->id)->where('name', 'manager')->firstOrFail(),
    ];
    $routes = [];

    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with((string) $route->getName(), 'platform.')) {
            continue;
        }

        $parameters = [];
        foreach ($route->parameterNames() as $name) {
            expect($values)->toHaveKey($name, message: "Add a value for {{$name}} (route {$route->getName()}) to platformRoutes().");
            $parameters[$name] = $values[$name]();
        }

        $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();
        $routes[] = [strtolower($method), route($route->getName(), $parameters), $route->getName()];
    }

    return $routes;
}

it('has console routes to check', function () {
    expect(platformRoutes($this->fixture))->not->toBeEmpty();
});

it('answers 404 on every console route to a business owner, a member and someone in two businesses', function () {
    foreach ([$this->fixture->a->owner, $this->fixture->b->cashier, $this->fixture->both] as $user) {
        foreach (platformRoutes($this->fixture) as [$method, $url, $name]) {
            app(TenantContext::class)->forget();
            $status = $this->actingAs($user)->{$method}($url)->getStatusCode();
            expect($status)->toBe(404, "{$name} answered {$status} to {$user->name}");
        }
    }
});

it('sends a guest to sign in from every console route', function () {
    foreach (platformRoutes($this->fixture) as [$method, $url, $name]) {
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
