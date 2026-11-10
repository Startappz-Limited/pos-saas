<?php

use App\Actions\Business\RemoveFromBusiness;
use App\Models\Business;
use App\Models\BusinessInvite;
use App\Models\BusinessMembership;
use App\Models\TillPin;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * `members:clean-removed` brings every removed membership to the state a
 * fresh removal leaves (stage 10a, step 1): rows from before this stage
 * (inactive, no role) are backfilled once, from `updated_at`, then handled
 * like any other removed row — till PIN gone, invites from before the
 * removal revoked (a re-invite sent since survives), the default business
 * moved on. A deactivated member who still holds a role is left alone, and
 * a second `--apply` changes nothing.
 */
beforeEach(function () {
    ownerRole();
    sharedRole('cashier');

    $this->business = testBusiness();
    $this->owner = User::factory()->create();
    $this->owner->assignRole('admin');
});

it('lists a legacy removal (inactive, no role) separately on a dry run, then backfills and settles it on --apply', function () {
    $member = User::factory()->create(['email' => 'legacy@example.test']);
    $member->assignRole('cashier');
    $membership = BusinessMembership::query()->where('business_id', $this->business->id)->where('user_id', $member->id)->first();
    // Simulate a pre-stage removal: inactive, no role, no removed_at.
    $member->syncRoles([]);
    $membership->forceFill(['status' => BusinessMembership::STATUS_INACTIVE])->save();

    TillPin::create(['business_id' => $this->business->id, 'user_id' => $member->id, 'pin_hash' => Hash::make('1234'), 'set_at' => now()]);
    $oldInvite = BusinessInvite::factory()->create(['email' => $member->email, 'created_at' => now()->subDay()]);
    $member->forceFill(['default_business_id' => $this->business->id])->save();

    $other = Business::factory()->create();
    app(TenantContext::class)->run($other, function () use ($member): void {
        ownerRole();
        $member->joinBusiness(testBusiness());
    });

    $dryRun = $this->artisan('members:clean-removed', ['--business' => $this->business->id]);
    $dryRun->expectsOutputToContain('Removed before this stage')->assertSuccessful();

    expect($membership->fresh()->removed_at)->toBeNull();
    // The best date there is: the row's updated_at *before* --apply backfills
    // removed_at (saving the row moves updated_at again, to now).
    $updatedAtBeforeBackfill = $membership->fresh()->updated_at;

    $this->artisan('members:clean-removed', ['--business' => $this->business->id, '--apply' => true])->assertSuccessful();

    $membership->refresh();
    expect($membership->removed_at)->not->toBeNull()
        ->and($membership->removed_at->equalTo($updatedAtBeforeBackfill))->toBeTrue();

    expect(TillPin::query()->where('user_id', $member->id)->exists())->toBeFalse()
        ->and($oldInvite->fresh()->revoked_at)->not->toBeNull()
        ->and($member->fresh()->default_business_id)->not->toBe($this->business->id);
});

it('keeps a re-invite sent after the removal, and leaves a deactivated member with a role untouched', function () {
    $removed = User::factory()->create(['email' => 'removed@example.test']);
    $removed->assignRole('cashier');
    app(RemoveFromBusiness::class)->execute($removed);

    $staleInvite = app(TenantContext::class)->run($this->business, fn () => BusinessInvite::query()->where('email', $removed->email)->first());
    $removedAt = BusinessMembership::query()->where('business_id', $this->business->id)->where('user_id', $removed->id)->value('removed_at');

    $freshInvite = app(TenantContext::class)->run($this->business, fn () => BusinessInvite::factory()->create([
        'email' => $removed->email,
        'created_at' => Carbon::parse($removedAt)->addMinute(),
    ]));

    $deactivated = User::factory()->create(['email' => 'deactivated@example.test']);
    $deactivated->assignRole('cashier');
    BusinessMembership::query()->where('business_id', $this->business->id)->where('user_id', $deactivated->id)
        ->update(['status' => BusinessMembership::STATUS_INACTIVE]);

    $this->artisan('members:clean-removed', ['--business' => $this->business->id, '--apply' => true])->assertSuccessful();

    expect($freshInvite->fresh()->revoked_at)->toBeNull()
        ->and($deactivated->fresh()->roles()->count())->toBe(1)
        ->and(BusinessMembership::query()->where('user_id', $deactivated->id)->first()->removed_at)->toBeNull();
});

it('changes nothing on a second --apply', function () {
    $removed = User::factory()->create(['email' => 'twice@example.test']);
    $removed->assignRole('cashier');
    app(RemoveFromBusiness::class)->execute($removed);

    $this->artisan('members:clean-removed', ['--business' => $this->business->id, '--apply' => true])->assertSuccessful();
    $after1 = BusinessMembership::query()->where('user_id', $removed->id)->first()->removed_at;

    $this->artisan('members:clean-removed', ['--business' => $this->business->id, '--apply' => true])->assertSuccessful();
    $after2 = BusinessMembership::query()->where('user_id', $removed->id)->first()->removed_at;

    expect($after1->equalTo($after2))->toBeTrue();
});

it('--all covers every business whatever its status', function () {
    $suspended = Business::factory()->create(['status' => Business::STATUS_SUSPENDED]);
    app(TenantContext::class)->run($suspended, function (): void {
        ownerRole();
        $member = User::factory()->create();
        $member->assignRole('admin');
        app(RemoveFromBusiness::class)->execute($member);
    });

    $this->artisan('members:clean-removed', ['--all' => true])->assertSuccessful()
        ->expectsOutputToContain((string) $suspended->id);
});
