<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsAppMessage;

class WhatsAppMessagePolicy
{
    /**
     * Allow users with full access to bypass all checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->can('whatsapp-messages.full-access')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('whatsapp-messages.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, WhatsAppMessage $whatsAppMessage): bool
    {
        if ($user->can('whatsapp-messages.view-all') && $user->canAccessShop($whatsAppMessage->shop_id)) {
            return true;
        }

        return $user->can('whatsapp-messages.view') && $user->canAccessShop($whatsAppMessage->shop_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('whatsapp-messages.create');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, WhatsAppMessage $whatsAppMessage): bool
    {
        if ($user->can('whatsapp-messages.delete-all') && $user->canAccessShop($whatsAppMessage->shop_id)) {
            return true;
        }

        return $user->can('whatsapp-messages.delete') && $user->canAccessShop($whatsAppMessage->shop_id);
    }
}
