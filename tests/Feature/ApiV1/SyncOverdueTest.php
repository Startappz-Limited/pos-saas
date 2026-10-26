<?php

use App\Models\Register;
use App\Notifications\TillSyncOverdue;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\Till;
use Tests\Support\TwoBusinesses;

/**
 * Last-sync tracking, Phase 7 (step 7): every pull or push notes the till's
 * sync; a paired till silent for 24 hours is overdue, its owners are told once
 * (an email and a banner) and the API says so; nothing is blocked.
 */
beforeEach(function () {
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));
    $this->fixture = TwoBusinesses::create();
    $this->tenant = app(TenantContext::class);
    $this->a = $this->fixture->a;
    $this->register = $this->tenant->run($this->a->business, fn () => Register::query()->orderBy('id')->firstOrFail());
    $this->tenant->forget();
    $this->token = Till::token($this->a->owner, $this->register);
    config(['sync.overdue_hours' => 24]);
});

function pullOverdue(string $token)
{
    Till::freshRequest();

    return test()->withToken($token)->getJson('/api/v1/sync/pull');
}

it('notes each sync, and says when the till had been silent for over a day', function () {
    pullOverdue($this->token)->assertOk()->assertJsonPath('meta.sync_overdue', false);
    expect($this->register->fresh()->last_synced_at->toDateTimeString())->toBe('2026-10-26 09:00:00');

    $this->travel(25)->hours();
    pullOverdue($this->token)->assertOk()->assertJsonPath('meta.sync_overdue', true);
    pullOverdue($this->token)->assertOk()->assertJsonPath('meta.sync_overdue', false);
});

it('tells the owners once about a till silent for a day, again after its next silence, and never about others', function () {
    Notification::fake();
    // An unpaired till in A and B's own till never count.
    pullOverdue($this->token);

    $this->travel(23)->hours();
    $this->artisan('sync:warn-overdue')->assertSuccessful();
    Notification::assertNothingSent();

    $this->travel(2)->hours();
    $this->artisan('sync:warn-overdue')->assertSuccessful();
    $this->artisan('sync:warn-overdue')->assertSuccessful();
    Notification::assertSentToTimes($this->a->owner, TillSyncOverdue::class, 1);
    Notification::assertNotSentTo([$this->a->cashier, $this->fixture->b->owner], TillSyncOverdue::class);

    // It syncs, then goes quiet again: one more warning.
    pullOverdue($this->token);
    $this->travel(25)->hours();
    $this->artisan('sync:warn-overdue')->assertSuccessful();
    Notification::assertSentToTimes($this->a->owner, TillSyncOverdue::class, 2);
});

it('counts a till that never synced from its pairing', function () {
    Notification::fake();
    $this->travel(25)->hours();

    $this->artisan('sync:warn-overdue')->assertSuccessful();

    Notification::assertSentToTimes($this->a->owner, TillSyncOverdue::class, 1);
});

it('shows owners a banner naming the silent till, and clears it once the till syncs', function () {
    pullOverdue($this->token);
    $this->travel(25)->hours();

    $this->actingAs($this->a->owner)->get(route('dashboard'))->assertOk()
        ->assertSee('till-sync-banner', false)->assertSee($this->register->name);
    $this->actingAs($this->a->cashier)->get(route('dashboard'))->assertDontSee('till-sync-banner', false);

    pullOverdue($this->token);
    $this->tenant->forget();
    $this->actingAs($this->a->owner)->get(route('dashboard'))->assertOk()->assertDontSee('till-sync-banner', false);
});

it('says which tills are overdue in the register list, and blocks nothing', function () {
    $this->travel(25)->hours();

    Till::freshRequest();
    $this->withToken(Till::signIn($this->a->owner))->getJson('/api/v1/registers')->assertOk()
        ->assertJsonPath('data.0.sync_overdue', true);

    Till::freshRequest();
    $this->withToken($this->token)->postJson('/api/v1/sync/push', ['items' => [[
        'client_uuid' => (string) Str::uuid(), 'type' => 'customer', 'recorded_at' => now()->toIso8601String(), 'data' => ['name' => 'Still trading'],
    ]]])->assertOk()->assertJsonPath('data.results.0.status', 'applied')->assertJsonPath('meta.sync_overdue', true);
});
