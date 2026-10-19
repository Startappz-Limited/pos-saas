<?php

namespace App\Services;

use App\Actions\Business\CopyRoleTemplates;
use App\Actions\Business\RemoveFromBusiness;
use App\Actions\CreateUserAction;
use App\Actions\UpdateUserAction;
use App\Enums\UserStatus;
use App\Models\BusinessMembership;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantFiles;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        private CreateUserAction $createUserAction,
        private UpdateUserAction $updateUserAction,
        private RemoveFromBusiness $removeFromBusiness,
        private TenantContext $tenant,
    ) {}

    /**
     * Get paginated users
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::query()
            ->inCurrentBusiness()
            ->withBusinessStatus()
            ->with(['roles'])
            ->latest();

        // Filter by status in this business (stage 3)
        if (! empty($filters['status'])) {
            $query->whereBusinessStatus($filters['status']);
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

        $users = $query->paginate($perPage);
        $users->getCollection()->each(fn (User $user) => $user->presentBusinessStatus());

        return $users;
    }

    /**
     * Get all active users
     */
    public function getActive(): Collection
    {
        return User::query()
            ->whereBusinessStatus(UserStatus::ACTIVE)
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

        // The status given is the person's status in this business (stage 3);
        // the account itself starts active.
        $status = $data['status'] ?? null;
        unset($data['status']);

        $user = $this->createUserAction->execute($data);

        // A person added here works in this business (stage 3).
        if ($this->tenant->business() !== null) {
            $user->joinBusiness($this->tenant->business(), auth()->user());

            if ($status !== null) {
                $this->setBusinessStatus($user, $status);
            }
        }

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
        // The business keeps at least one owner.
        if (isset($data['roles']) && ! in_array(CopyRoleTemplates::OWNER_ROLE, $data['roles'], true) && $this->isLastOwner($user)) {
            throw ValidationException::withMessages(['roles' => [__('The business needs at least one owner.')]]);
        }

        // Handle profile photo upload if provided
        if (isset($data['profile_photo']) && $data['profile_photo']) {
            // Delete old photo
            $this->deleteProfilePhoto($user);
            $data['profile_photo'] = $this->uploadProfilePhoto($data['profile_photo']);
        }

        // The status is the person's status in this business (stage 3).
        if (isset($data['status'])) {
            $this->setBusinessStatus($user, $data['status']);
        }
        unset($data['status']);

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
    /**
     * Removes the person from the current business; the login stays (it may
     * belong to other businesses).
     */
    public function delete(User $user): bool
    {
        $this->removeFromBusiness->execute($user);

        return true;
    }

    private function isLastOwner(User $user): bool
    {
        return $user->isBusinessOwner()
            && User::query()->inCurrentBusiness()->role(CopyRoleTemplates::OWNER_ROLE)->count() <= 1;
    }

    /**
     * Change user status
     */
    public function changeStatus(User $user, UserStatus $status): User
    {
        $this->setBusinessStatus($user, $status);

        return $user->fresh();
    }

    /**
     * The person's status in the current business (stage 3): suspending or
     * deactivating here doesn't touch their login or their other businesses.
     */
    private function setBusinessStatus(User $user, UserStatus|string $status): void
    {
        BusinessMembership::query()
            ->where('business_id', $this->tenant->id())
            ->where('user_id', $user->id)
            ->update(['status' => $status instanceof UserStatus ? $status->value : $status]);
    }

    /**
     * Get user statistics
     */
    public function getStatistics(): array
    {
        $thirtyDaysAgo = now()->subDays(30);

        // Statuses are per business (stage 3).
        $byStatus = BusinessMembership::query()
            ->where('business_id', $this->tenant->id() ?? 0)
            ->selectRaw('status, COUNT(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        $stats = User::query()->inCurrentBusiness()->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN email_verified_at IS NOT NULL THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as recent
        ', [$thirtyDaysAgo])->first();
        $stats->active = $byStatus[UserStatus::ACTIVE->value] ?? 0;
        $stats->inactive = $byStatus[UserStatus::INACTIVE->value] ?? 0;
        $stats->suspended = $byStatus[UserStatus::SUSPENDED->value] ?? 0;

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
        // Private, under the business's folder and within its quota (stage 1c).
        return app(TenantFiles::class)->storePrivate($file, 'profile-photos', 'profile_photo');
    }

    /**
     * Removes the user's photo, wherever it is: private, or public from
     * before stage 1c.
     */
    private function deleteProfilePhoto(User $user): void
    {
        if ($user->profile_photo) {
            Storage::disk(TenantFiles::PRIVATE_DISK)->delete($user->profile_photo);
            Storage::disk(TenantFiles::PUBLIC_DISK)->delete($user->profile_photo);
        }
    }

    /**
     * Search users by query
     */
    public function search(string $query, int $limit = 10): Collection
    {
        return User::query()
            ->inCurrentBusiness()
            ->where(fn ($q) => $q->where('name', 'like', "%{$query}%")->orWhere('email', 'like', "%{$query}%"))
            ->whereBusinessStatus(UserStatus::ACTIVE)
            ->limit($limit)
            ->get();
    }

    /**
     * Get users by role
     */
    public function getByRole(string $role): Collection
    {
        return User::role($role)
            ->inCurrentBusiness()
            ->active()
            ->orderBy('name')
            ->get();
    }
}
