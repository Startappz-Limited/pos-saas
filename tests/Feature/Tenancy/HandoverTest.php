<?php

use App\Models\User;
use App\Notifications\OwnerConfirmationCode;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Notification;
use Tests\Support\TwoBusinesses;

/**
 * Handing a business over (decision 4.9, stage 3): only the founding owner,
 * only to an existing co-owner, only with the emailed code; the previous owner
 * stays a co-owner; the founding owner is protected from other owners.
 */
beforeEach(function () {
    Notification::fake();
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->a = $this->fixture->a;
    $this->tenant = app(TenantContext::class);

    $this->a->business->update(['primary_owner_id' => $this->a->owner->id]);
    $this->coOwner = User::factory()->memberOf($this->a->business)->create(['name' => 'Co Owner']);
    $this->tenant->run($this->a->business, fn () => $this->coOwner->assignRole('admin'));
    $this->tenant->forget();
});

function handoverCode(User $owner, string $targetUuid): string
{
    test()->actingAs($owner)->post(route('business.handover.code'), ['owner' => $targetUuid])->assertRedirect(route('business.data'));

    $code = null;
    Notification::assertSentTo($owner, OwnerConfirmationCode::class, function (OwnerConfirmationCode $notification) use (&$code): bool {
        $code = $notification->code();

        return true;
    });

    return $code;
}

it('hands the business to a co-owner with the emailed code, and the previous owner stays a co-owner', function () {
    $code = handoverCode($this->a->owner, $this->coOwner->uuid);

    $this->actingAs($this->a->owner)->post(route('business.handover.confirm'), ['code' => $code])
        ->assertRedirect(route('business.data'))->assertSessionHas('success');

    expect($this->a->business->fresh()->primary_owner_id)->toBe($this->coOwner->id)
        ->and($this->tenant->run($this->a->business, fn () => $this->a->owner->fresh()->isBusinessOwner()))->toBeTrue();
});

it('refuses a wrong code and changes nothing', function () {
    handoverCode($this->a->owner, $this->coOwner->uuid);

    $this->actingAs($this->a->owner)->post(route('business.handover.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');

    expect($this->a->business->fresh()->primary_owner_id)->toBe($this->a->owner->id);
});

it('refuses someone who is not a co-owner of this business', function () {
    $member = User::factory()->memberOf($this->a->business)->create();

    $this->actingAs($this->a->owner)->post(route('business.handover.code'), ['owner' => $member->uuid])->assertSessionHasErrors('owner');
    $this->actingAs($this->a->owner)->post(route('business.handover.code'), ['owner' => $this->fixture->b->owner->uuid])->assertSessionHasErrors('owner');

    Notification::assertNotSentTo($this->a->owner, OwnerConfirmationCode::class);
});

it('refuses the handover if the co-owner was removed after the code was sent', function () {
    $code = handoverCode($this->a->owner, $this->coOwner->uuid);
    $this->tenant->run($this->a->business, fn () => $this->coOwner->removeRole('admin'));
    $this->tenant->forget();

    $this->actingAs($this->a->owner)->post(route('business.handover.confirm'), ['code' => $code])->assertSessionHasErrors('code');

    expect($this->a->business->fresh()->primary_owner_id)->toBe($this->a->owner->id);
});

it('lets only the founding owner start a handover', function () {
    $this->actingAs($this->coOwner)->post(route('business.handover.code'), ['owner' => $this->a->owner->uuid])->assertForbidden();
});

it('protects the founding owner from other owners until the handover', function () {
    $this->tenant->run($this->a->business, function (): void {
        expect($this->coOwner->can('delete', $this->a->owner))->toBeFalse()
            ->and($this->coOwner->can('update', $this->a->owner))->toBeFalse();
    });
    $this->tenant->forget();

    $code = handoverCode($this->a->owner, $this->coOwner->uuid);
    $this->actingAs($this->a->owner)->post(route('business.handover.confirm'), ['code' => $code]);

    $this->tenant->run($this->a->business->fresh(), function (): void {
        expect($this->coOwner->fresh()->can('delete', $this->a->owner->fresh()))->toBeTrue()
            ->and($this->a->owner->fresh()->can('delete', $this->coOwner->fresh()))->toBeFalse();
    });
});
