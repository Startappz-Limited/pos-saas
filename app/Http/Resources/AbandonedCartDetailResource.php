<?php

namespace App\Http\Resources;

use App\Models\AbandonedCart;
use App\Models\AbandonedCartItem;
use App\Models\Alert;
use Illuminate\Http\Request;

/**
 * One abandoned cart with its lines, timeline and reminders.
 *
 * `recovery_link` is the live signed URL that restores the customer's cart. It
 * is included only when the caller may act on the cart (policy
 * `viewRecoveryLink`); otherwise it is null.
 *
 * @mixin AbandonedCart
 */
class AbandonedCartDetailResource extends AbandonedCartResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canSeeLink = (bool) $request->user()?->can('viewRecoveryLink', $this->resource);

        return array_merge(parent::toArray($request), [
            'recovery_link' => $canSeeLink ? $this->checkout_link : null,
            'can_be_converted' => $this->can_be_converted,
            'has_unmatched_items' => $this->has_unmatched_items,
            'ecommerce_order' => $this->whenLoaded('ecommerceOrder', fn () => $this->ecommerceOrder ? [
                'uuid' => $this->ecommerceOrder->uuid,
                'order_number' => $this->ecommerceOrder->order_number,
            ] : null),
            'items' => $this->items->map(fn (AbandonedCartItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'platform_product_id' => $item->platform_product_id,
                'platform_variation_id' => $item->platform_variation_id,
                'product' => $item->product ? [
                    'uuid' => $item->product->uuid,
                    'name' => $item->product->name,
                ] : null,
                'variation_id' => $item->variation_id,
                'is_matched' => $item->isSellable(),
                'quantity' => $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'line_total' => (string) $item->line_total,
                'image_url' => $item->image_url,
            ])->values(),
            'timeline' => $this->whenLoaded('timeline', fn () => $this->timeline->map(fn (Alert $alert) => self::alert($alert))->values()),
            'reminders' => $this->whenLoaded('reminders', fn () => $this->reminders->map(fn (Alert $alert) => self::alert($alert))->values()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function alert(Alert $alert): array
    {
        return [
            'uuid' => $alert->uuid,
            'type' => $alert->type->value,
            'title' => $alert->title,
            'message' => $alert->message,
            'severity' => $alert->severity->value,
            'data' => $alert->data,
            'scheduled_at' => $alert->scheduled_at?->toIso8601String(),
            'is_resolved' => (bool) $alert->is_resolved,
            'is_overdue' => $alert->isOverdue(),
            'created_by' => $alert->relationLoaded('creator') ? $alert->creator?->name : null,
            'created_at' => $alert->created_at?->toIso8601String(),
        ];
    }
}
