<?php

use App\Models\User;

/**
 * The users list shows the actions people may take, as their policy allows,
 * not a permission named after the route that doesn't exist for `users`
 * (stage 10a, gap 1). Owner protection is unchanged (only an owner may edit
 * or remove another owner); nobody — the owner included — changes their own
 * role. (A broader "can't edit anyone whose role holds more permissions"
 * check was tried and dropped — decided with the user, 10 November 2026 —
 * because the existing, tested design lets a narrowly-permissioned people
 * manager edit a cashier even though the real seeded cashier role holds
 * many unrelated operational permissions the manager doesn't: see
 * tests/Feature/Tenancy/PeopleTest.php and the stage file's "Points to
 * confirm" / Revisions.)
 */

/**
 * The resource's show and destroy routes share one URL (GET vs DELETE), so a
 * bare href check can't tell "View" from "Delete" apart — only a delete
 * button's own hidden `_method=DELETE` form does.
 */
function hasDeleteButtonFor(string $html, string $href): bool
{
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
    $xpath = new DOMXPath($dom);

    foreach ($xpath->query("//form[@action=\"{$href}\"]") as $form) {
        if ($xpath->query('.//input[@name="_method" and @value="DELETE"]', $form)->length > 0) {
            return true;
        }
    }

    return false;
}
beforeEach(function () {
    ownerRole();
    sharedRole('cashier');
    sharedRole('manager');

    $this->owner = User::factory()->create(['name' => 'The Owner']);
    $this->owner->assignRole('admin');

    $this->manager = User::factory()->create(['name' => 'A Manager']);
    $this->manager->assignRole('manager');
    sharedRole('manager')->givePermissionTo(['users.view', 'users.update']);

    $this->cashier = User::factory()->create(['name' => 'A Cashier']);
    $this->cashier->assignRole('cashier');

    $this->otherManager = User::factory()->create(['name' => 'Another Manager']);
    $this->otherManager->assignRole('manager');
    // Holds a permission this business's manager template doesn't: stronger.
    $this->otherManager->givePermissionTo('settings.update');
});

it('shows an owner View and Edit on every member\'s row, and Delete on members they may delete', function () {
    $html = $this->actingAs($this->owner)->get(route('users.index'))->assertOk()->getContent();

    foreach ([$this->manager, $this->cashier, $this->otherManager] as $member) {
        expect($html)->toContain(route('users.edit', $member))->toContain(route('users.show', $member));
        expect(hasDeleteButtonFor($html, route('users.destroy', $member)))->toBeTrue("Delete for {$member->name}");
    }

    // Not themselves, not the primary owner (here, the owner is primary).
    expect(hasDeleteButtonFor($html, route('users.destroy', $this->owner)))->toBeFalse();
});

it('shows a manager View and Edit on a cashier\'s and a peer manager\'s row, but only View on the owner\'s, and no Delete', function () {
    $html = $this->actingAs($this->manager)->get(route('users.index'))->assertOk()->getContent();

    foreach ([$this->cashier, $this->otherManager] as $member) {
        expect($html)->toContain(route('users.edit', $member))->toContain(route('users.show', $member));
    }

    expect($html)->toContain(route('users.show', $this->owner))->not->toContain(route('users.edit', $this->owner));

    expect(hasDeleteButtonFor($html, route('users.destroy', $this->cashier)))->toBeFalse()
        ->and(hasDeleteButtonFor($html, route('users.destroy', $this->otherManager)))->toBeFalse();
});

it('shows a cashier with no user permissions no buttons, and refuses the list without users.view', function () {
    $this->actingAs($this->cashier)->get(route('users.index'))->assertForbidden();
});

it('can\'t raise a cashier to a role the manager doesn\'t fully hold', function () {
    $this->actingAs($this->manager);
    sharedRole('admin'); // the owner template, held by nobody here but named.

    $response = $this->put(route('users.update', $this->cashier), [
        'name' => $this->cashier->name, 'email' => $this->cashier->email, 'status' => 'active', 'role_id' => sharedRole('admin')->id,
    ]);

    $response->assertSessionHasErrors();
});

it('refuses anyone, the owner included, changing their own role', function () {
    $this->actingAs($this->owner);

    $response = $this->put(route('users.update', $this->owner), [
        'name' => $this->owner->name, 'email' => $this->owner->email, 'status' => 'active', 'role_id' => sharedRole('manager')->id,
    ]);

    $response->assertSessionHasErrors('roles');
    expect($this->owner->fresh()->hasRole('admin'))->toBeTrue();
});

it('lets the owner edit their own other details without touching their role', function () {
    $this->actingAs($this->owner);

    $this->put(route('users.update', $this->owner), [
        'name' => 'Renamed Owner', 'email' => $this->owner->email, 'status' => 'active', 'role_id' => sharedRole('admin')->id,
    ])->assertRedirect();

    expect($this->owner->fresh()->name)->toBe('Renamed Owner')
        ->and($this->owner->fresh()->hasRole('admin'))->toBeTrue();
});

it('refuses the owner deleting themselves; only an owner deletes anyone', function () {
    expect($this->owner->can('delete', $this->owner))->toBeFalse()
        ->and($this->manager->can('delete', $this->cashier))->toBeFalse()
        ->and($this->owner->can('delete', $this->cashier))->toBeTrue();
});
