<?php

namespace App\Http\Controllers;

use App\Actions\Role\ResetRoleToTemplate;
use App\Actions\Role\SetRoleActive;
use App\Actions\Role\SyncRolePermissions;
use App\Http\Requests\ResetRoleRequest;
use App\Http\Requests\SetRoleActiveRequest;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of roles.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Role::class);

        // Holders in this business only (roles are held per business).
        $roles = Role::with('permissions')
            ->withCount('holders')
            ->when($request->query('status') === 'active', fn ($query) => $query->active())
            ->when($request->query('status') === 'inactive', fn ($query) => $query->where('is_active', false))
            ->paginate(15)
            ->withQueryString();

        return view('roles.index', compact('roles'));
    }

    /**
     * Show the form for creating a new role.
     */
    public function create()
    {
        $this->authorize('create', Role::class);

        $permissions = Permission::all();

        return view('roles.create', compact('permissions'));
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(StoreRoleRequest $request, SyncRolePermissions $sync)
    {
        $validated = $request->validated();

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'business_id' => app(TenantContext::class)->id(),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        $sync->execute($role, $request->user(), $validated['permissions'] ?? []);

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role created successfully.');
    }

    /**
     * Display the specified role.
     */
    public function show(Role $role)
    {
        $this->authorize('view', $role);

        // Holders in this business only (roles are held per business).
        $role->load(['permissions', 'holders' => fn ($query) => $query->inCurrentBusiness()
            ->select('users.id', 'users.name', 'users.email', 'users.profile_photo', 'users.status', 'users.email_verified_at', 'users.created_at')]);

        return view('roles.show', compact('role'));
    }

    /**
     * Show the form for editing the specified role.
     */
    public function edit(Role $role)
    {
        $this->authorize('update', $role);

        $role->load('permissions')->loadCount('holders');

        $permissions = Permission::all();

        return view('roles.edit', compact('role', 'permissions'));
    }

    /**
     * Update the specified role in storage.
     */
    public function update(UpdateRoleRequest $request, Role $role, SyncRolePermissions $sync, SetRoleActive $setActive)
    {
        $validated = $request->validated();

        $role->update(['name' => $validated['name']]);
        $sync->execute($role, $request->user(), $validated['permissions'] ?? []);

        if (array_key_exists('is_active', $validated)) {
            $setActive->execute($role, (bool) $validated['is_active']);
        }

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role updated successfully.');
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy(Role $role)
    {
        $this->authorize('delete', $role);

        // Still held by someone in this business.
        if ($role->holders()->exists()) {
            return redirect()
                ->route('roles.index')
                ->with('error', 'Cannot delete role that has assigned users.');
        }

        $role->delete();

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role deleted successfully.');
    }

    /**
     * Display permissions for a role.
     */
    public function permissions(Role $role)
    {
        $this->authorize('view', $role);

        $permissions = Permission::all()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0];
        });

        $rolePermissions = $role->permissions->pluck('id')->toArray();

        return view('roles.permissions', compact('role', 'permissions', 'rolePermissions'));
    }

    /**
     * Update permissions for a role.
     */
    public function updatePermissions(UpdateRolePermissionsRequest $request, Role $role, SyncRolePermissions $sync)
    {
        $sync->execute($role, $request->user(), $request->validated()['permissions'] ?? []);

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role permissions updated successfully.');
    }

    /**
     * Reset a business's copy of a role template to the template's current
     * permissions (Phase 5).
     */
    public function reset(ResetRoleRequest $request, Role $role, ResetRoleToTemplate $reset)
    {
        $reset->execute($role);

        return redirect()
            ->route('roles.show', $role)
            ->with('success', __('The role was reset to its default permissions.'));
    }

    /**
     * Switch a role on or off (Phase 5). Switching it off is confirmed first.
     */
    public function setActive(SetRoleActiveRequest $request, Role $role, SetRoleActive $setActive)
    {
        $active = $request->boolean('active');
        $setActive->execute($role, $active);

        return redirect()
            ->back(fallback: route('roles.show', $role))
            ->with('success', $active ? __('The role is switched on.') : __('The role is switched off.'));
    }
}
