<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use App\Rules\GrantableRole;
use App\Rules\StrongPassword;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->only(['status', 'role', 'search']);
        $users = $this->userService->getPaginated($filters, $request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', new StrongPassword],
            'shop_id' => ['nullable', 'exists:shops,id'],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'roles' => ['nullable', 'array'],
            'roles.*' => [new GrantableRole($request->user(), byName: true)],
        ]);

        $user = $this->userService->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data' => $user->load('roles')->presentBusinessStatus(),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $user->load(['roles', 'shops'])->presentBusinessStatus();

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', new StrongPassword([(string) $user->email])],
            'shop_id' => ['nullable', 'exists:shops,id'],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'roles' => ['nullable', 'array'],
            'roles.*' => [new GrantableRole($request->user(), byName: true, holder: $user)],
        ]);

        $user = $this->userService->update($user, $validated);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => $user->load('roles')->presentBusinessStatus(),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        // Only owners remove people, and only from this business (stage 3).
        $this->authorize('delete', $user);

        $this->userService->delete($user);

        return response()->json([
            'success' => true,
            'message' => 'User removed from the business.',
        ]);
    }

    public function roles(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $roles = Role::select('id', 'name')->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    public function shops(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $shops = Shop::select('id', 'name', 'uuid')->get();

        return response()->json([
            'success' => true,
            'data' => $shops,
        ]);
    }

    public function statuses(): JsonResponse
    {
        $statuses = UserStatus::options();

        return response()->json([
            'success' => true,
            'data' => $statuses,
        ]);
    }
}
