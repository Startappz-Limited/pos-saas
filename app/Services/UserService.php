<?php

namespace App\Services;

use App\Actions\CreateUserAction;
use App\Actions\DeleteUserAction;
use App\Actions\UpdateUserAction;
use App\Enums\UserStatus;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserService
{
    public function __construct(
        private CreateUserAction $createUserAction,
        private UpdateUserAction $updateUserAction,
        private DeleteUserAction $deleteUserAction
    ) {}

    /**
     * Get paginated users
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::query()
            ->with(['roles'])
            ->latest();

        // Only users of the viewer's own business; super-admins stay hidden
        if (auth()->user() !== null) {
            $query->visibleTo(auth()->user());
        }

        // Filter by status
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by role name. Not Spatie's ->role() scope: with per-business
        // roles it resolves the name against one "current team" and throws
        // when the role is not found there.
        if (! empty($filters['role'])) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $filters['role']));
        }

        // Search by name or email
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Get all active users
     */
    public function getActive(): Collection
    {
        return User::active()
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a new user
     */
    public function create(array $data): User
    {
        // Handle profile photo upload if provided
        if (isset($data['profile_photo']) && $data['profile_photo']) {
            $data['profile_photo'] = $this->uploadProfilePhoto($data['profile_photo']);
        }

        $data['business_id'] = $this->businessIdForNewUser($data['shop_ids'] ?? []);

        $user = $this->createUserAction->execute($data);

        // Assign roles if provided
        if (! empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        // A new admin is a new shop owner: give them their own business
        $user->ensureBusiness();
        User::forgetShopAccessCache();

        return $user;
    }

    /**
     * Update an existing user
     */
    public function update(User $user, array $data): User
    {
        // Handle profile photo upload if provided
        if (isset($data['profile_photo']) && $data['profile_photo']) {
            // Delete old photo
            if ($user->profile_photo) {
                Storage::disk('public')->delete($user->profile_photo);
            }
            $data['profile_photo'] = $this->uploadProfilePhoto($data['profile_photo']);
        }

        $user = $this->updateUserAction->execute($user, $data);

        // Sync roles if provided
        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        $user->ensureBusiness();
        User::forgetShopAccessCache();

        return $user;
    }

    /**
     * Staff join the business of whoever creates them. A super-admin belongs to
     * no business, so their new users join the business of the first shop
     * they are linked to (or none, for a new admin, who gets their own).
     *
     * @param  array<int, int|string>  $shopIds
     */
    private function businessIdForNewUser(array $shopIds): ?int
    {
        $creator = auth()->user();

        if ($creator !== null && ! $creator->isSuperAdmin()) {
            return $creator->currentBusinessId();
        }

        if ($shopIds === []) {
            return null;
        }

        $businessId = Shop::query()->whereIn('id', $shopIds)->orderBy('id')->value('business_id');

        return $businessId === null ? null : (int) $businessId;
    }

    /**
     * Delete a user and erase their personal data (right to erasure). The
     * business keeps its records: sales and the like stay, with the creator
     * link cleared by the foreign keys, and the audit trail keeps what
     * happened but no longer who it was (AuditErasure).
     */
    public function delete(User $user): bool
    {
        $userId = $user->getKey();

        if ($user->profile_photo) {
            Storage::disk('public')->delete($user->profile_photo);
        }

        $user->signOutEverywhere();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        $deleted = $this->deleteUserAction->execute($user);

        // After the delete, so the audit row of the deletion is erased too
        app(AuditErasure::class)->eraseUser($userId);

        return $deleted;
    }

    /**
     * Change user status
     */
    public function changeStatus(User $user, UserStatus $status): User
    {
        $user->update(['status' => $status]);

        return $user->fresh();
    }

    /**
     * Get user statistics
     */
    public function getStatistics(): array
    {
        $thirtyDaysAgo = now()->subDays(30);

        $stats = User::query()->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
            SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
            SUM(CASE WHEN email_verified_at IS NOT NULL THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as recent
        ", [$thirtyDaysAgo])->first();

        return [
            'total' => (int) $stats->total,
            'active' => (int) $stats->active,
            'inactive' => (int) $stats->inactive,
            'suspended' => (int) $stats->suspended,
            'verified' => (int) $stats->verified,
            'recent' => (int) $stats->recent,
        ];
    }

    /**
     * Upload profile photo
     */
    private function uploadProfilePhoto($file): string
    {
        return $file->store('profile-photos', 'public');
    }

    /**
     * Search users by query
     */
    public function search(string $query, int $limit = 10): Collection
    {
        return User::where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->active()
            ->limit($limit)
            ->get();
    }

    /**
     * Get users by role
     */
    public function getByRole(string $role): Collection
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', $role))
            ->active()
            ->orderBy('name')
            ->get();
    }
}
