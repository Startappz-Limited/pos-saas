<?php

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Role;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\TwoBusinesses;

/**
 * Role templates in the platform console, Phase 5 (B6): the only place the
 * shared templates and the owner role change. A template edit reaches new
 * businesses and "Reset to default" but leaves existing copies unchanged,
 * unless "Apply to all copies" is confirmed; a business's copy can be reset on
 * its behalf. Every action audited. (Owners get a 404: PlatformAccessTest;
 * businesses can't change templates: Roles/RolePolicyTest.)
 */
beforeEach(function () {
    migrateAuditDatabase();
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->operator = platformOperator();
    $this->tenant = app(TenantContext::class);
    $this->tenant->forget();
    $this->template = Role::withoutGlobalScopes()->whereNull('business_id')->where('name', 'manager')->firstOrFail();
});

function copyIn(Business $business, string $name = 'manager'): Role
{
    return Role::withoutGlobalScopes()->where('business_id', $business->id)->where('name', $name)->firstOrFail();
}

/**
 * @return array<int, string>
 */
function namesOf(Role $role): array
{
    return $role->fresh()->permissions()->pluck('name')->sort()->values()->all();
}

function lastConsoleRow(string $action): ?AuditLog
{
    return AuditLog::query()->latest('id')->get()->first(fn ($row) => in_array($action, $row->tags ?? [], true));
}

it('lists the shared templates with how many businesses hold a copy', function () {
    $copies = $this->actingAs($this->operator)->get(route('platform.roles.index'))->assertOk()
        ->assertSeeText('Inventory clerk')->assertSeeText('Owner')->viewData('copies');

    expect((int) $copies[$this->template->id])->toBe(DB::table('roles')->where('template_id', $this->template->id)->count())
        ->and((int) $copies[$this->template->id])->toBeGreaterThanOrEqual(2)
        ->and(lastConsoleRow('role_templates.listed'))->not->toBeNull();
});

it('changes a template for new businesses and Reset to default, leaving existing copies unchanged', function () {
    $a = $this->fixture->a->business;
    $copyBefore = namesOf(copyIn($a));
    // A permission the template lacks, and one it has to take away.
    $added = Permission::query()->whereNotIn('id', $this->template->permissions()->pluck('permissions.id'))->orderBy('name')->firstOrFail();
    $removed = $this->template->permissions()->orderBy('name')->firstOrFail();
    $keep = $this->template->permissions()->where('permissions.id', '!=', $removed->id)->pluck('permissions.id')->all();

    $this->travel(1)->minutes();
    $this->actingAs($this->operator)->put(route('platform.roles.update', $this->template), ['permissions' => [...$keep, $added->id]])
        ->assertRedirect(route('platform.roles.edit', $this->template))->assertSessionHas('success');

    expect(namesOf($this->template))->toContain($added->name)->not->toContain($removed->name)
        // Existing copies: unchanged.
        ->and(namesOf(copyIn($a)))->toBe($copyBefore)
        ->and(namesOf(copyIn($this->fixture->b->business)))->toBe($copyBefore)
        // A new business gets the new template.
        ->and(namesOf(copyIn(Business::factory()->create())))->toBe(namesOf($this->template))
        // Touched, so the seeder keeps the edit.
        ->and($this->template->fresh()->updated_at->isAfter(now()->subSeconds(5)))->toBeTrue();

    $audit = lastConsoleRow('role_template.updated');
    expect($audit->new_values['added'])->toBe([$added->name])
        ->and($audit->new_values['removed'])->toBe([$removed->name])
        ->and($audit->user_id)->toBe($this->operator->id);

    // Reset to default in A now applies the edited template, in A only.
    $this->tenant->forget();
    $this->actingAs($this->fixture->a->owner)->post(route('roles.reset', copyIn($a)), ['confirm' => '1'])->assertSessionHas('success');
    expect(namesOf(copyIn($a)))->toBe(namesOf($this->template))
        ->and(namesOf(copyIn($this->fixture->b->business)))->toBe($copyBefore);
});

it('changes the owner role for every business\'s owners, only with the confirmation', function () {
    $admin = sharedRole('admin');
    $all = Permission::query()->pluck('id')->all();
    $withoutAudit = Permission::query()->where('name', '!=', 'audit.export')->pluck('id')->all();

    $this->actingAs($this->operator)->put(route('platform.roles.update', $admin), ['permissions' => $withoutAudit])
        ->assertSessionHasErrors('confirm');
    expect(namesOf($admin))->toContain('audit.export');

    $this->actingAs($this->operator)->put(route('platform.roles.update', $admin), ['permissions' => $withoutAudit, 'confirm' => '1'])
        ->assertSessionHasNoErrors();

    $can = fn (Business $business, $owner) => $this->tenant->run($business, fn () => $owner->fresh()->can('audit.export'));
    expect($can($this->fixture->a->business, $this->fixture->a->owner))->toBeFalse()
        ->and($can($this->fixture->b->business, $this->fixture->b->owner))->toBeFalse();

    $this->actingAs($this->operator)->put(route('platform.roles.update', $admin), ['permissions' => $all, 'confirm' => '1']);
    expect($can($this->fixture->a->business, $this->fixture->a->owner))->toBeTrue();
});

it('applies a template to every copy only when confirmed, and audits it once', function () {
    $a = copyIn($this->fixture->a->business);
    $b = copyIn($this->fixture->b->business);
    $a->syncPermissions(['sales.view']);
    $b->syncPermissions(['sales.void']);

    $this->actingAs($this->operator)->post(route('platform.roles.apply', $this->template))->assertSessionHasErrors('confirm');
    expect(namesOf($a))->toBe(['sales.view']);

    $this->actingAs($this->operator)->post(route('platform.roles.apply', $this->template), ['confirm' => '1'])->assertSessionHas('success');

    $copies = DB::table('roles')->where('template_id', $this->template->id)->count();
    expect(namesOf($a))->toBe(namesOf($this->template))
        ->and(namesOf($b))->toBe(namesOf($this->template))
        ->and(lastConsoleRow('role_template.applied_to_copies')->new_values['copies'])->toBe($copies);

    // The owner role has no copies.
    $this->actingAs($this->operator)->post(route('platform.roles.apply', sharedRole('admin')), ['confirm' => '1'])->assertSessionHas('error');
});

it('resets one business\'s copy on its behalf, in that business only, and audits it', function () {
    $b = $this->fixture->b->business;
    copyIn($b)->syncPermissions(['sales.void']);
    copyIn($this->fixture->a->business)->syncPermissions(['sales.view']);

    $this->actingAs($this->operator)->post(route('platform.businesses.roles.reset', [$b, copyIn($b)->uuid]))->assertSessionHasErrors('confirm');
    $this->actingAs($this->operator)->post(route('platform.businesses.roles.reset', [$b, copyIn($b)->uuid]), ['confirm' => '1'])
        ->assertRedirect(route('platform.businesses.show', $b))->assertSessionHas('success');

    expect(namesOf(copyIn($b)))->toBe(namesOf($this->template))
        ->and(namesOf(copyIn($this->fixture->a->business)))->toBe(['sales.view'])
        ->and(lastConsoleRow('role.reset')->business_id)->toBe($b->id);

    // The business page lists its roles with the reset button.
    $this->actingAs($this->operator)->get(route('platform.businesses.show', $b))->assertOk()
        ->assertSee(route('platform.businesses.roles.reset', [$b, copyIn($b)->uuid]), false);
});

it('refuses resetting a custom role, or another business\'s copy through this business', function () {
    $b = $this->fixture->b->business;
    $custom = $this->tenant->run($b, fn () => Role::create(['name' => 'helper', 'guard_name' => 'web', 'business_id' => $b->id]));
    $this->tenant->forget();

    $this->actingAs($this->operator)->post(route('platform.businesses.roles.reset', [$b, $custom->uuid]), ['confirm' => '1'])->assertSessionHas('error');
    $this->actingAs($this->operator)->post(route('platform.businesses.roles.reset', [$b, copyIn($this->fixture->a->business)->uuid]), ['confirm' => '1'])
        ->assertNotFound();
});

it('edits only shared templates: a business\'s role is a 404 here', function () {
    $copy = copyIn($this->fixture->b->business);

    $this->actingAs($this->operator)->get(route('platform.roles.edit', $copy->uuid))->assertNotFound();
    $this->actingAs($this->operator)->put(route('platform.roles.update', $copy->uuid), ['permissions' => []])->assertNotFound();
    $this->actingAs($this->operator)->post(route('platform.roles.apply', $copy->uuid), ['confirm' => '1'])->assertNotFound();
});
