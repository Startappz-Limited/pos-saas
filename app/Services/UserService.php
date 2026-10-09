<?php

namespace App\Services;

use App\Actions\CreateUserAction;
use App\Actions\DeleteUserAction;
use App\Actions\UpdateUserAction;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
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

        // Filter by status
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by role
        if (! empty($filters['role'])) {
            $query->role($filters['role']);
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

        $user = $this->createUserAction->execute($data);

        // Assign roles if provided
        if (! empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

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

        return $user;
    }

    /**
     * Delete a user
     */
    public function delete(User $user): bool
    {
        // Delete profile photo
        if ($user->profile_photo) {
            Storage::disk('public')->delete($user->profile_photo);
        }

        return $this->deleteUserAction->execute($user);
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
        return User::role($role)
            ->active()
            ->orderBy('name')
            ->get();
    }
}
