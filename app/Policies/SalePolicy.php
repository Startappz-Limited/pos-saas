<?php

namespace App\Policies;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\User;
use App\Support\Sales\VoidWindow;
use Illuminate\Auth\Access\Response;

class SalePolicy
{
    /**
     * Allow users with full access to bypass all checks, except the void
     * window, which holds the owner too (stage 10, step 2).
     */
    public function before(User $user, string $ability): ?bool
    {
        if (in_array($ability, ['void', 'requestVoid'], true)) {
            return null;
        }

        if ($user->can('sales.full-access')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('sales.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Sale $sale): bool
    {
        if ($user->can('sales.view-all') && $user->canAccessShop($sale->shop_id)) {
            return true;
        }

        return $user->can('sales.view') && $user->canAccessShop($sale->shop_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('sales.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Sale $sale): bool
    {
        if ($user->can('sales.edit-all') && $user->canAccessShop($sale->shop_id)) {
            return true;
        }

        return $user->can('sales.update') &&
            $user->canAccessShop($sale->shop_id) &&
            $sale->created_at->diffInHours(now()) < 24;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Sale $sale): bool
    {
        if ($user->can('sales.delete-all') && $user->canAccessShop($sale->shop_id)) {
            return true;
        }

        return $user->can('sales.delete') && $user->canAccessShop($sale->shop_id);
    }

    /**
     * Determine whether the user can collect payment for the sale.
     */
    public function collectPayment(User $user, Sale $sale): bool
    {
        if (! $user->canAccessShop($sale->shop_id)) {
            return false;
        }

        // Collecting is also the accountant's job: credit collection or taking payments (hotfix fix/api-authz).
        return $user->can('sales.edit-all') || $user->can('sales.update')
            || $user->can('credit-sales.collect') || $user->can('payments.create');
    }

    /**
     * Determine whether the user can complete a pending sale. Unlike editing, there
     * is no 24-hour limit: a delivery or held sale is completed when it is handed
     * over, which may be days later (hotfix fix/api-authz).
     */
    public function complete(User $user, Sale $sale): bool
    {
        return $sale->status === SaleStatus::PENDING->value
            && $user->can('sales.create')
            && $user->canAccessShop($sale->shop_id);
    }

    /**
     * Whether the user can void the sale now (stage 10, step 2): only
     * `sales.void` (or `sales.full-access`) allows it (403 otherwise), never
     * with a return that isn't rejected (422 `sale_has_returns`, step 4), and
     * only inside the business's void window, which holds the owner too: past
     * it the refusal is 422 with the time-limit message.
     */
    public function void(User $user, Sale $sale): Response|bool
    {
        if ($sale->status === SaleStatus::VOIDED->value
            || ! ($user->can('sales.void') || $user->can('sales.full-access'))
            || ! $user->canAccessShop($sale->shop_id)) {
            return false;
        }

        if (VoidWindow::hasOpenReturns($sale)) {
            return Response::denyWithStatus(422, __(VoidWindow::RETURNS_REFUSAL), 'sale_has_returns');
        }

        $window = app(VoidWindow::class);
        $now = now();

        return $window->allows($sale, $now) ? Response::allow() : Response::denyWithStatus(422, $window->refusal($sale, $now), 'void_window_closed');
    }

    /**
     * Whether the user can ask for the sale to be voided (stage 10, step 2):
     * someone who sells in its shop (`sales.create`) without `sales.void`
     * (holders void it themselves), while it isn't voided. The window and an
     * open request are the request's own checks (422).
     */
    public function requestVoid(User $user, Sale $sale): bool
    {
        return $sale->status !== SaleStatus::VOIDED->value
            && ! $sale->trashed()
            && $user->can('sales.create')
            && ! ($user->can('sales.void') || $user->can('sales.full-access'))
            && $user->canAccessShop($sale->shop_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Sale $sale): bool
    {
        return $user->can('sales.restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Sale $sale): bool
    {
        return $user->can('sales.delete-all');
    }
}
