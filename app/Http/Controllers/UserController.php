<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private UserService $userService)
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of users
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->only(['status', 'role', 'search']);
        $users = $this->userService->getPaginated($filters, $request->get('per_page', 15));
        $statistics = $this->userService->getStatistics();
        $roles = Role::assignableBy(auth()->user())->select('id', 'name')->get();

        return view('users.index', compact('users', 'statistics', 'roles', 'filters'));
    }

    /**
     * Show the form for creating a new user
     */
    public function create(): View
    {
        $this->authorize('create', User::class);

        $roles = Role::assignableBy(auth()->user())->select('id', 'name')->get();
        $shops = Shop::select('id', 'name')->get();
        $statuses = UserStatus::options();

        return view('users.create', compact('roles', 'shops', 'statuses'));
    }

    /**
     * Store a newly created user
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $user = $this->userService->create($request->validated());

        return redirect()
            ->route('users.show', $user)
            ->with('success', 'User created successfully.');
    }

    /**
     * Display the specified user
     */
    public function show(User $user): View
    {
        $this->authorize('view', $user);

        $user->load(['roles', 'shops', 'creator', 'updater']);

        return view('users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified user
     */
    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        $roles = Role::assignableBy(auth()->user())->select('id', 'name')->get();
        $shops = Shop::select('id', 'name')->get();
        $statuses = UserStatus::options();
        $user->load(['roles', 'shops']);

        return view('users.edit', compact('user', 'roles', 'shops', 'statuses'));
    }

    /**
     * Update the specified user
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $user = $this->userService->update($user, $request->validated());

        return redirect()
            ->route('users.show', $user)
            ->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified user
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $this->userService->delete($user);

        return redirect()
            ->route('users.index')
            ->with('success', 'User deleted successfully.');
    }

    /**
     * Activate user
     */
    public function activate(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $this->userService->changeStatus($user, UserStatus::ACTIVE);

        return back()->with('success', 'User activated successfully.');
    }

    /**
     * Deactivate user
     */
    public function deactivate(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $this->userService->changeStatus($user, UserStatus::INACTIVE);

        return back()->with('success', 'User deactivated successfully.');
    }

    /**
     * Suspend user
     */
    public function suspend(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot suspend your own account.');
        }

        $this->userService->changeStatus($user, UserStatus::SUSPENDED);

        return back()->with('success', 'User suspended successfully.');
    }
}
