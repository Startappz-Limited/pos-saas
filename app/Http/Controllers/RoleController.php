<?php

namespace App\Http\Controllers;

use App\Actions\Role\SyncRolePermissions;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of roles.
     */
    public function index()
    {
        $this->authorize('viewAny', Role::class);

        // Holders in this business only (roles are held per business).
        $roles = Role::with('permissions')
            ->withCount('holders')
            ->paginate(15);

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
    public function update(UpdateRoleRequest $request, Role $role, SyncRolePermissions $sync)
    {
        $validated = $request->validated();

        $role->update(['name' => $validated['name']]);
        $sync->execute($role, $request->user(), $validated['permissions'] ?? []);

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
}
