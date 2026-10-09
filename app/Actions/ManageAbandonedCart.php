<?php

namespace App\Actions;

use App\Enums\AbandonedCartStatus;
use App\Enums\AlertType;
use App\Exceptions\AbandonedCartActionException;
use App\Jobs\AbandonedCartWriteBackJob;
use App\Models\AbandonedCart;
use App\Models\Alert;
use App\Models\User;
use App\Services\Integration\AbandonedCartWriteBack;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Staff follow-up on an abandoned cart, shared by the web screens and the API so
 * the two cannot drift: status, assignment, notes, reminders and opt-out.
 */
class ManageAbandonedCart
{
    /**
     * @param  array{status?: string|null, assigned_to?: int|null, note?: string|null}  $data
     *
     * @throws AbandonedCartActionException
     */
    public function update(AbandonedCart $cart, array $data, User $user): AbandonedCart
    {
        return DB::transaction(function () use ($cart, $data, $user): AbandonedCart {
            if (array_key_exists('status', $data) && $data['status'] !== null) {
                $status = AbandonedCartStatus::from($data['status']);

                if ($status !== $cart->status) {
                    if ($cart->status === AbandonedCartStatus::Converted) {
                        throw new AbandonedCartActionException('A converted cart is closed; its status cannot change.');
                    }

                    if (! in_array($status, AbandonedCartStatus::manual(), true)) {
                        throw new AbandonedCartActionException("A cart cannot be set to \"{$status->label()}\" by hand.");
                    }

                    $from = $cart->status;
                    $cart->status = $status;

                    if ($status === AbandonedCartStatus::Contacted) {
                        $cart->last_contacted_at ??= now();
                    }

                    $cart->save();
                    $cart->logActivity(
                        "Status changed to {$status->label()}",
                        "From {$from->label()}, by {$user->name}",
                        ['event' => 'status_changed', 'from' => $from->value, 'to' => $status->value],
                        userId: $user->id,
                    );
                }
            }

            if (array_key_exists('assigned_to', $data) && (int) $data['assigned_to'] !== (int) $cart->assigned_to) {
                $assignee = $data['assigned_to'] ? User::find($data['assigned_to']) : null;

                if ($data['assigned_to'] && (! $assignee || ! $assignee->canAccessShop($cart->shop_id))) {
                    throw new AbandonedCartActionException('That user cannot work on this shop\'s carts.');
                }

                $cart->forceFill(['assigned_to' => $assignee?->id])->save();
                $cart->logActivity(
                    $assignee ? "Assigned to {$assignee->name}" : 'Unassigned',
                    "By {$user->name}",
                    ['event' => 'assigned', 'assigned_to' => $assignee?->id],
                    userId: $user->id,
                );
            }

            if (! empty($data['note'])) {
                $this->addNote($cart, $data['note'], $user);
            }

            return $cart->refresh();
        });
    }

    public function addNote(AbandonedCart $cart, string $message, User $user): Alert
    {
        return $cart->logActivity(
            'Note on abandoned cart #'.$cart->platform_cart_id,
            $message,
            type: AlertType::CART_NOTE,
            userId: $user->id,
        );
    }

    /**
     * A follow-up reminder for staff (shows on the calendar), not a customer message.
     */
    public function addReminder(AbandonedCart $cart, string $title, ?string $message, Carbon|string $scheduledAt, User $user): Alert
    {
        $alert = $cart->logActivity(
            $title,
            (string) $message,
            ['customer_name' => $cart->customer_name, 'cart' => $cart->platform_cart_id],
            type: AlertType::CART_REMINDER,
            userId: $user->id,
        );

        $alert->forceFill(['scheduled_at' => $scheduledAt, 'is_read' => false])->save();

        return $alert;
    }

    /**
     * @throws AbandonedCartActionException
     */
    public function resolveReminder(AbandonedCart $cart, Alert $alert, User $user): void
    {
        if ($alert->alertable_type !== AbandonedCart::class
            || (int) $alert->alertable_id !== (int) $cart->id
            || $alert->type !== AlertType::CART_REMINDER) {
            throw new AbandonedCartActionException('This reminder does not belong to this cart.');
        }

        $alert->resolve('Dismissed by user', $user->id);
    }

    /**
     * The customer asked not to be contacted. Stops POS messages at once and
     * asks the website to unsubscribe them from its reminders.
     */
    public function optOut(AbandonedCart $cart, User $user, ?string $reason = null): AbandonedCart
    {
        if ($cart->opted_out_at !== null) {
            return $cart;
        }

        DB::transaction(function () use ($cart, $user, $reason): void {
            $cart->forceFill(['opted_out_at' => now()])->save();
            $cart->logActivity(
                'Customer opted out of reminders',
                trim("Recorded by {$user->name}. ".($reason ?? '')),
                ['event' => 'opted_out'],
                userId: $user->id,
            );

            AbandonedCartWriteBackJob::dispatchSafely(
                $cart,
                AbandonedCartWriteBack::STATUS_UNSUBSCRIBED,
                null,
                $reason ?: "Opted out via POS by {$user->name}",
            );
        });

        return $cart->refresh();
    }
}
