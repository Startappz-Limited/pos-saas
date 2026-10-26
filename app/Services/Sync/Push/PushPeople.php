<?php

namespace App\Services\Sync\Push;

use App\Enums\ApiErrorCode;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * The person a pushed item names (a cashier who signed in on the till with
 * their PIN): a member of the business who may reach the till's shop.
 */
final class PushPeople
{
    public function __construct(private TenantContext $tenant) {}

    /**
     * $uuid's user, or the pushing user when none is named.
     *
     * @throws PushRefused
     */
    public function staff(?string $uuid, PushItem $item, string $field = 'cashier'): User
    {
        if ($uuid === null || $uuid === '') {
            return $item->user;
        }

        $user = User::query()->inCurrentBusiness()->where('uuid', $uuid)->first();

        if ($user === null || ! in_array($item->register->shop_id, $this->tenant->accessibleShopIdsFor($user), true)) {
            throw new PushRefused(ApiErrorCode::ValidationFailed, __('That person can\'t work at this till.'), [$field => [__('That person can\'t work at this till.')]]);
        }

        return $user;
    }
}
