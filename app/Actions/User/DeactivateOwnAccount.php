<?php

namespace App\Actions\User;

use App\Actions\Business\CopyRoleTemplates;
use App\Enums\UserStatus;
use App\Models\BusinessMembership;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A person closing their own account (stage 3): deactivation instead of
 * deletion. The login stays (its sales, stock movements and audit entries keep
 * their author, and its memberships stay for the platform operator to restore);
 * it can't sign in, and every API token is revoked. The last owner of a
 * business can't: the business would have nobody to run it.
 */
class DeactivateOwnAccount
{
    public function __construct(private TenantContext $tenant) {}

    /**
     * @throws ValidationException when the user is the last owner of a business
     */
    public function execute(User $user): void
    {
        $soleOwnerOf = $user->usableBusinesses()
            ->filter(fn ($business): bool => $this->tenant->run($business, fn (): bool => $user->isBusinessOwner()
                && User::query()->inCurrentBusiness()->whereBusinessStatus(BusinessMembership::STATUS_ACTIVE)
                    ->role(CopyRoleTemplates::OWNER_ROLE)->count() <= 1))
            ->pluck('name');

        if ($soleOwnerOf->isNotEmpty()) {
            throw ValidationException::withMessages([
                'password' => [__('You are the only owner of :businesses. Make someone else an owner first.', ['businesses' => $soleOwnerOf->join(', ')])],
            ])->errorBag('userDeletion');
        }

        DB::transaction(function () use ($user): void {
            $user->forceFill(['status' => UserStatus::INACTIVE])->save();
            $user->tokens()->delete();
        });
    }
}
