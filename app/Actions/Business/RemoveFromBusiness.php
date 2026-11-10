<?php

namespace App\Actions\Business;

use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use App\Models\Business;
use App\Models\BusinessInvite;
use App\Models\BusinessMembership;
use App\Models\Shop;
use App\Models\TillPin;
use App\Models\User;
use App\Support\Tenancy\DefaultBusinessAudit;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Removes a person from the current business (stage 3, extended stage 10a
 * step 1): their membership is made inactive and `removed_at` is set, their
 * roles and shop assignments here go, and their `/api/v1` tokens for it are
 * revoked, so they lose access at once. The login itself stays: it may
 * belong to other businesses, and its records here keep their author. Only
 * an accepted re-invite clears `removed_at` and restores the membership.
 *
 * `settle()` is the part also run by `members:clean-removed`, for a
 * membership removed before this stage (backfilled `removed_at`) or
 * reprocessed because an earlier run didn't finish: till PIN, pending
 * invites and the default business, all keyed off when the removal
 * happened rather than "now".
 */
class RemoveFromBusiness
{
    public function __construct(private TenantContext $tenant) {}

    public function execute(User $user): void
    {
        $businessId = $this->tenant->id();
        $removedAt = now();

        DB::transaction(function () use ($user, $businessId, $removedAt): void {
            BusinessMembership::query()
                ->where('business_id', $businessId)
                ->where('user_id', $user->id)
                ->update(['status' => BusinessMembership::STATUS_INACTIVE, 'removed_at' => $removedAt]);

            // Roles are held per business: this detaches only this business's.
            $user->syncRoles([]);

            // Run by an owner, who sees every shop of the business.
            $user->shops()->detach(Shop::query()->pluck('id'));

            PersonalAccessToken::query()
                ->where('tokenable_type', $user->getMorphClass())
                ->where('tokenable_id', $user->id)
                ->where('business_id', $businessId)
                ->delete();
        });

        $this->settle($user, $businessId, $removedAt, 'member_removed');
        $this->auditRemoval($user, $businessId);
    }

    /**
     * Brings a removed membership to the state a fresh removal leaves:
     * deletes the business till PIN, revokes invites to the person's email
     * for this business created before the removal (so a re-invite sent
     * since survives), and moves the default business away when it still
     * points here. Safe to call again on an already-settled removal — every
     * step is a no-op once it has nothing left to do.
     */
    public function settle(User $user, int $businessId, CarbonInterface $removedAt, string $reason): void
    {
        TillPin::query()->where('business_id', $businessId)->where('user_id', $user->id)->delete();

        // <=, not <: a live removal's own invite query and its removedAt
        // (both "now") can land in the same second, and nothing can have
        // been sent "since" a removal that is still happening.
        BusinessInvite::query()
            ->where('business_id', $businessId)
            ->where('email', $user->email)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('created_at', '<=', $removedAt)
            ->update(['revoked_at' => now()]);

        if ((int) $user->default_business_id === $businessId) {
            $this->moveDefaultBusiness($user, $businessId, $reason);
        }
    }

    private function moveDefaultBusiness(User $user, int $businessId, string $reason): void
    {
        $from = Business::find($businessId);

        $nextId = BusinessMembership::query()
            ->where('user_id', $user->id)
            ->where('business_id', '!=', $businessId)
            ->where('status', BusinessMembership::STATUS_ACTIVE)
            ->whereNull('removed_at')
            ->whereHas('business', fn ($query) => $query->where('status', Business::STATUS_ACTIVE))
            ->orderBy('joined_at')
            ->value('business_id');

        $user->forceFill(['default_business_id' => $nextId])->save();

        DefaultBusinessAudit::record($user, $from, $nextId !== null ? Business::find($nextId) : null, $reason);

        if ($nextId === null) {
            PersonalAccessToken::query()
                ->where('tokenable_type', $user->getMorphClass())
                ->where('tokenable_id', $user->id)
                ->whereNull('business_id')
                ->delete();
        }
    }

    private function auditRemoval(User $user, int $businessId): void
    {
        $actor = Auth::user();

        AuditableEvent::dispatch(
            auditableType: User::class,
            auditableId: $user->id,
            auditableUuid: $user->uuid,
            event: AuditEvent::DELETED,
            oldValues: null,
            newValues: ['business_id' => $businessId],
            userId: $actor?->id,
            userName: $actor?->name,
            userEmail: $actor?->email,
            tags: ['membership', 'member_removed'],
            businessId: $businessId,
        );
    }
}
