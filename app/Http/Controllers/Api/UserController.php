<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use App\Rules\KeepsBusinessOwner;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
            'password' => ['required', Password::defaults()],
            'shop_id' => ['nullable', Rule::in(Shop::query()->pluck('id'))],
            'status' => ['nullable', 'string'],
            'roles' => ['nullable', 'array'],
            'roles.*' => [Rule::in(Role::assignableBy($request->user())->pluck('name'))],
        ]);

        $user = $this->userService->create($this->withShopIds($validated));

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data' => $user->load('roles'),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $user->load(['roles', 'shop']);

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
            'password' => ['nullable', Password::defaults()],
            'shop_id' => ['nullable', Rule::in(Shop::query()->pluck('id'))],
            'status' => ['nullable', 'string', KeepsBusinessOwner::status($user, $request->user())],
            'roles' => ['nullable', 'array', KeepsBusinessOwner::roles($user, $request->user())],
            'roles.*' => [Rule::in(Role::assignableBy($request->user())->pluck('name'))],
        ]);

        $user = $this->userService->update($user, $this->withShopIds($validated));

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => $user->load('roles'),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        $this->userService->delete($user);

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.',
        ]);
    }

    /**
     * The app sends a single shop_id; the service links users through
     * shop_ids. Without this the shop was silently dropped, leaving every
     * user created from the app unable to work.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function withShopIds(array $validated): array
    {
        if (array_key_exists('shop_id', $validated)) {
            $validated['shop_ids'] = $validated['shop_id'] === null ? [] : [(int) $validated['shop_id']];
            unset($validated['shop_id']);
        }

        return $validated;
    }

    public function roles(): JsonResponse
    {
        $roles = Role::assignableBy(auth()->user())->select('id', 'name')->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    public function shops(): JsonResponse
    {
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
