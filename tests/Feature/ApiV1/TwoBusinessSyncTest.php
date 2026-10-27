<?php

use App\Models\Customer;
use App\Models\Register;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\Till;
use Tests\Support\TwoBusinesses;

/**
 * Phase 7 acceptance: a person who works in two businesses syncs each on its
 * own till, and nothing crosses between them (decision 4.5: a separate store,
 * token and register per business).
 */
beforeEach(function () {
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));
    $this->fixture = TwoBusinesses::create();
    $this->tenant = app(TenantContext::class);
});

it('lets a person in two businesses sync each on its own till, independently', function () {
    $both = $this->fixture->both;
    $tokens = [];

    foreach (['a' => $this->fixture->a, 'b' => $this->fixture->b] as $key => $side) {
        $register = $this->tenant->run($side->business, fn () => Register::defaultFor($side->shops[0]->id));
        $this->tenant->forget();
        Till::pair(Till::signIn($side->owner), $register, "till-{$key}")->assertOk();

        // The same person signs in on each business's till.
        Till::freshRequest();
        $tokens[$key] = $this->postJson('/api/v1/auth/sign-in', [
            'email' => $both->email, 'password' => 'password', 'device_name' => "Till {$key}",
            'business' => $side->business->uuid, 'register' => $register->uuid, 'device_id' => "till-{$key}",
        ])->assertOk()->json('data.token');
    }
    $this->travel(2)->seconds();

    // Each pushes a customer into its own business only.
    foreach (['a', 'b'] as $key) {
        Till::freshRequest();
        $this->withToken($tokens[$key])->postJson('/api/v1/sync/push', ['items' => [[
            'client_uuid' => (string) Str::uuid(), 'type' => 'customer', 'recorded_at' => now()->toIso8601String(),
            'data' => ['name' => "Pushed in {$key}"],
        ]]])->assertOk()->assertJsonPath('data.results.0.status', 'applied');
    }
    $this->travel(2)->seconds();

    foreach (['a' => $this->fixture->a, 'b' => $this->fixture->b] as $key => $side) {
        $other = $key === 'a' ? 'b' : 'a';
        Till::freshRequest();
        $pull = $this->withToken($tokens[$key])->getJson('/api/v1/sync/pull')->assertOk();
        $names = collect($pull->json('data.customers'))->pluck('name');

        expect($names)->toContain("Pushed in {$key}")->not->toContain("Pushed in {$other}")
            ->and($pull->json('data.settings.business.uuid'))->toBe((string) $side->business->uuid)
            ->and($this->tenant->run($side->business, fn () => Customer::query()->where('name', "Pushed in {$key}")->exists()))->toBeTrue()
            ->and($this->tenant->run($side->business, fn () => Customer::query()->where('name', "Pushed in {$other}")->exists()))->toBeFalse();
    }
});
