<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
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

        $roles = Role::with('permissions')
            ->withCount('users')
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
    public function store(Request $request)
    {
        $this->authorize('create', Role::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name', 'regex:/^[a-z0-9-]+$/'],
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create(['name' => $validated['name']]);

        if (isset($validated['permissions'])) {
            $permissions = Permission::whereIn('id', $validated['permissions'])->get();
            $role->syncPermissions($permissions);
        }

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

        $role->load(['permissions', 'users:id,name,email,avatar,is_active,created_at']);

        return view('roles.show', compact('role'));
    }

    /**
     * Show the form for editing the specified role.
     */
    public function edit(Role $role)
    {
        $this->authorize('update', $role);

        $role->load('permissions');

        $permissions = Permission::all();

        return view('roles.edit', compact('role', 'permissions'));
    }

    /**
     * Update the specified role in storage.
     */
    public function update(Request $request, Role $role)
    {
        $this->authorize('update', $role);

        // Prevent editing super-admin role
        if ($role->name === 'super-admin') {
            return redirect()
                ->route('roles.index')
                ->with('error', 'Super Admin role cannot be modified.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update(['name' => $validated['name']]);

        if (isset($validated['permissions'])) {
            $permissions = Permission::whereIn('id', $validated['permissions'])->get();
            $role->syncPermissions($permissions);
        } else {
            $role->syncPermissions([]);
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

        // Prevent deleting super-admin role
        if ($role->name === 'super-admin') {
            return redirect()
                ->route('roles.index')
                ->with('error', 'Super Admin role cannot be deleted.');
        }

        // Check if role has users
        if ($role->users()->count() > 0) {
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
    public function updatePermissions(Request $request, Role $role)
    {
        $this->authorize('update', $role);

        // Prevent modifying super-admin permissions
        if ($role->name === 'super-admin') {
            return redirect()
                ->route('roles.index')
                ->with('error', 'Super Admin permissions cannot be modified.');
        }

        $validated = $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role permissions updated successfully.');
    }
}
