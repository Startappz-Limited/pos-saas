<?php

use App\Actions\Business\AcceptBusinessInvite;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessInvite;
use App\Models\BusinessMembership;
use App\Models\Role;
use App\Models\TillPin;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\TwoBusinesses;

/**
 * Deleting a member removes only their membership of that business (stage
 * 10a, step 1): `removed_at` is set, their till PIN and pending invites for
 * it go, a default business pointing here moves on (audited, as the
 * switcher is), their roles and shops here are cleared, and the removal
 * itself is audited. Everything in another business they belong to stays.
 * Only an accepted re-invite brings them back; activating, deactivating or
 * suspending them by hand is refused.
 */
beforeEach(function () {
    migrateAuditDatabase();
    $this->fixture = TwoBusinesses::create();
});

it('removes a member from one business only, settling till PIN, invites and the default business, all audited', function () {
    $a = $this->fixture->a;
    $b = $this->fixture->b;
    $person = $this->fixture->both; // owner-free member of both, cashier in each.

    TillPin::create(['business_id' => $a->business->id, 'user_id' => $person->id, 'pin_hash' => Hash::make('1234'), 'set_at' => now()]);
    app(TenantContext::class)->run($a->business, fn () => BusinessInvite::factory()->create(['email' => $person->email]));
    $person->forceFill(['default_business_id' => $a->business->id])->save();
    $boundToken = $person->createToken('a-bound', ['*']);
    $boundToken->accessToken->forceFill(['business_id' => $a->business->id])->save();
    $unboundToken = $person->createToken('unbound', ['*']);

    app(TenantContext::class)->run($a->business, function () use ($person, $a): void {
        $this->actingAs($a->owner);
        $this->delete(route('users.destroy', $person))->assertRedirect();
    });

    $membership = BusinessMembership::query()->where('business_id', $a->business->id)->where('user_id', $person->id)->first();
    expect($membership->status)->toBe(BusinessMembership::STATUS_INACTIVE)
        ->and($membership->removed_at)->not->toBeNull();

    expect(TillPin::query()->where('business_id', $a->business->id)->where('user_id', $person->id)->exists())->toBeFalse();
    expect(app(TenantContext::class)->run($a->business, fn () => BusinessInvite::query()->where('email', $person->email)->first()->revoked_at))->not->toBeNull();

    $person->refresh();
    expect($person->default_business_id)->toBe($b->business->id);
    expect(PersonalAccessToken::query()->whereKey($boundToken->accessToken->id)->exists())->toBeFalse();
    expect(PersonalAccessToken::query()->whereKey($unboundToken->accessToken->id)->exists())->toBeTrue();

    // B is untouched: role and shop stay, the person still signs in to it.
    expect(DB::table('shop_user')->where('user_id', $person->id)->where('shop_id', $b->shops[0]->id)->exists())->toBeTrue();
    app(TenantContext::class)->run($b->business, function () use ($person): void {
        expect($person->hasRole('cashier'))->toBeTrue();
    });

    $removal = AuditLog::query()->where('auditable_type', User::class)->where('auditable_id', $person->id)
        ->whereJsonContains('tags', 'member_removed')->first();
    expect($removal)->not->toBeNull()->and($removal->business_id)->toBe($a->business->id);

    $switch = AuditLog::query()->where('auditable_type', User::class)->where('auditable_id', $person->id)
        ->whereJsonContains('tags', 'default_business_changed')->latest('id')->first();
    expect($switch->new_values['default_business_id'])->toBe($b->business->id)
        ->and($switch->new_values['reason'])->toBe('member_removed');
});

it('deletes unbound tokens when the removed member has no usable business left', function () {
    $a = $this->fixture->a;
    $owner = $a->owner;
    $lone = app(TenantContext::class)->run($a->business, fn () => User::factory()->memberOf($a->business)->create());
    app(TenantContext::class)->run($a->business, fn () => $lone->assignRole('cashier'));
    $unbound = $lone->createToken('unbound', ['*']);

    app(TenantContext::class)->run($a->business, function () use ($owner, $lone): void {
        $this->actingAs($owner);
        $this->delete(route('users.destroy', $lone))->assertRedirect();
    });

    expect($lone->fresh()->default_business_id)->toBeNull()
        ->and(PersonalAccessToken::query()->whereKey($unbound->accessToken->id)->exists())->toBeFalse();
});

it('refuses to delete the owner, and nobody deletes themselves', function () {
    $a = $this->fixture->a;

    app(TenantContext::class)->run($a->business, function () use ($a): void {
        $this->actingAs($a->owner);
        $this->delete(route('users.destroy', $a->owner))->assertForbidden();
    });
});

describe('a removed member can\'t be reactivated by hand', function () {
    beforeEach(function () {
        $this->a = $this->fixture->a;
        app(TenantContext::class)->run($this->a->business, function (): void {
            $this->actingAs($this->a->owner);
            $this->delete(route('users.destroy', $this->a->cashier))->assertRedirect();
        });
    });

    it('refuses Activate on the web with "Re-invite them instead."', function () {
        app(TenantContext::class)->run($this->a->business, function (): void {
            $this->actingAs($this->a->owner);
            $this->post(route('users.activate', $this->a->cashier))->assertSessionHas('error', 'Re-invite them instead.');
        });

        expect(BusinessMembership::query()->where('business_id', $this->a->business->id)->where('user_id', $this->a->cashier->id)->first()->status)
            ->toBe(BusinessMembership::STATUS_INACTIVE);
    });

    it('refuses a status update on the flat API with 422 {success:false,message}', function () {
        $response = app(TenantContext::class)->run($this->a->business, function () {
            $this->actingAs($this->a->owner);

            return $this->putJson(route('api.users.update', $this->a->cashier), [
                'name' => $this->a->cashier->name, 'status' => 'active',
            ]);
        });

        $response->assertStatus(422)->assertJson(['success' => false, 'message' => 'Re-invite them instead.']);
    });

    it('is brought back only by accepting a re-invite, which clears removed_at', function () {
        $email = $this->a->cashier->email;

        $invite = app(TenantContext::class)->run($this->a->business, fn () => BusinessInvite::factory()->create([
            'email' => $email,
            'role_id' => Role::findByName('cashier')->id,
            'shop_ids' => [$this->a->shops[0]->id],
        ]));

        app(AcceptBusinessInvite::class)->execute($invite, $this->a->cashier);

        $membership = BusinessMembership::query()->where('business_id', $this->a->business->id)->where('user_id', $this->a->cashier->id)->first();
        expect($membership->removed_at)->toBeNull()
            ->and($membership->status)->toBe(BusinessMembership::STATUS_ACTIVE);

        app(TenantContext::class)->run($this->a->business, function (): void {
            expect($this->a->cashier->fresh()->hasRole('cashier'))->toBeTrue();
        });
    });
});
