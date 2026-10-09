<?php

namespace App\Http\Resources;

use App\Models\AbandonedCart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An abandoned cart as listed. NEVER includes the recovery link — that only
 * appears on the single-cart resource, and only for staff who may act on it.
 *
 * Money is emitted as "0.00" strings, like every other money field the mobile
 * app reads.
 *
 * @mixin AbandonedCart
 */
class AbandonedCartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'platform' => $this->platform,
            'platform_cart_id' => $this->platform_cart_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'platform_status' => $this->platform_status,
            'shop' => $this->whenLoaded('shop', fn () => [
                'uuid' => $this->shop->uuid,
                'name' => $this->shop->name,
            ]),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'uuid' => $this->customer->uuid,
                'name' => $this->customer->name,
            ] : null),
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'user_type' => $this->user_type,
            'capture_source' => $this->capture_source,
            'currency' => $this->currency,
            'subtotal' => (string) $this->subtotal,
            'tax_total' => (string) $this->tax_total,
            'total' => (string) $this->total,
            'coupon_code' => $this->coupon_code,
            'items_count' => $this->whenCounted('items', fn () => (int) $this->items_count),
            'has_recovery_link' => $this->getRawOriginal('checkout_link') !== null,
            'reminders_sent' => (int) $this->reminders_sent,
            'last_activity' => $this->last_activity,
            'assigned_to' => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ] : null),
            'sale' => $this->whenLoaded('sale', fn () => $this->sale ? [
                'uuid' => $this->sale->uuid,
                'invoice_number' => $this->sale->invoice_number,
            ] : null),
            'platform_order_id' => $this->platform_order_id,
            'is_opted_out' => $this->opted_out_at !== null,
            'abandoned_at' => $this->abandoned_at?->toIso8601String(),
            'recovered_at' => $this->recovered_at?->toIso8601String(),
            'converted_at' => $this->converted_at?->toIso8601String(),
            'last_contacted_at' => $this->last_contacted_at?->toIso8601String(),
            'opted_out_at' => $this->opted_out_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
