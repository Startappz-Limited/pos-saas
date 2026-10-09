<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Closure;
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

        // The global roles plus the viewer's own business's roles
        $roles = Role::visibleTo(auth()->user())
            ->with(['permissions', 'business:id,name'])
            ->withCount('users')
            ->orderByRaw('business_id is not null')
            ->orderBy('name')
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
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', $this->uniqueRoleName()],
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        // An admin's role belongs to their business; a super-admin's is global
        $role = Role::create([
            'name' => $validated['name'],
            'business_id' => $request->user()->isSuperAdmin() ? null : $request->user()->currentBusinessId(),
        ]);

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

        // Only the users the viewer may see: a global role such as admin is
        // held by users of every business. (The column list used to name
        // avatar/is_active, which do not exist, so this page always failed.)
        $role->load([
            'permissions',
            'users' => fn ($query) => $query->visibleTo(auth()->user())
                ->select('users.id', 'users.name', 'users.email', 'users.profile_photo', 'users.status', 'users.created_at'),
        ]);

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
            'name' => ['required', 'string', 'max:255', $this->uniqueRoleName($role)],
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        // System roles are looked up by name, so renaming one breaks them
        if ($role->isSystemRole() && $validated['name'] !== $role->name) {
            return redirect()
                ->route('roles.index')
                ->with('error', 'System roles cannot be renamed.');
        }

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

        // Prevent deleting a role the code refers to by name (super-admin, admin)
        if ($role->isSystemRole()) {
            return redirect()
                ->route('roles.index')
                ->with('error', 'System roles cannot be deleted.');
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

    /**
     * Role names only need to be unique within a business, but a business
     * role may not share a name with a global role: names are resolved
     * against both (Spatie teams), so the lookup would become ambiguous. A
     * global role (created by a super-admin) must be unique everywhere.
     */
    private function uniqueRoleName(?Role $ignore = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignore): void {
            $user = auth()->user();
            $businessId = $ignore !== null
                ? $ignore->business_id
                : ($user->isSuperAdmin() ? null : $user->currentBusinessId());

            $taken = Role::query()
                ->where('name', $value)
                ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->getKey()))
                ->when($businessId !== null, fn ($q) => $q->where(fn ($q) => $q->whereNull('business_id')->orWhere('business_id', $businessId)))
                ->exists();

            if ($taken) {
                $fail(__('A role with this name already exists.'));
            }
        };
    }
}
