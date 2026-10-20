<?php

namespace App\Actions\Business;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Hands the current business to an existing co-owner (decision 4.9, stage 3):
 * they become the founding owner. The previous owner stays a co-owner (and,
 * no longer protected, may leave or be removed). The one-time code that
 * confirms it is checked by the caller.
 */
class HandOverBusiness
{
    public function __construct(private TenantContext $tenant) {}

    /**
     * Owners of the current business other than $from, active in it.
     *
     * @return Collection<int, User>
     */
    public function candidates(User $from): Collection
    {
        return User::query()->inCurrentBusiness()
            ->whereKeyNot($from->id)
            ->whereHas('memberships', fn ($membership) => $membership->where('business_id', $this->tenant->id())->where('status', 'active'))
            ->role(CopyRoleTemplates::OWNER_ROLE)
            ->orderBy('name')->get();
    }

    public function execute(User $from, User $to): void
    {
        if (! $this->candidates($from)->contains('id', $to->id)) {
            throw ValidationException::withMessages(['owner' => [__('Ownership can only pass to an existing co-owner of this business.')]]);
        }

        $this->tenant->business()->update(['primary_owner_id' => $to->id]);
    }
}
