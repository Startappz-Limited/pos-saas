<?php

namespace App\Actions\Baileys;

use App\Models\BaileysMessage;
use App\Models\BaileysSession;
use App\Models\EcommerceOrder;
use App\Support\BaileysJid;

/**
 * Phase 2: notify a customer over Baileys when an ecommerce order arrives.
 *
 * Independent of the official WhatsApp template flow.
 */
class NotifyCustomerOfEcommerceOrder
{
    public function __construct(protected SendBaileysMessage $sendMessage) {}

    public function execute(EcommerceOrder $order): ?BaileysMessage
    {
        if (! config('baileys.notify_on_ecommerce_order', true)) {
            return null;
        }

        $phone = $order->customer_phone ?? $order->customer?->phone ?? null;

        if (! $phone) {
            return null;
        }

        $jid = BaileysJid::fromPhone($phone);

        if (! $jid) {
            return null;
        }

        $session = BaileysSession::query()
            ->where('shop_id', $order->shop_id)
            ->connected()
            ->latest('connected_at')
            ->first();

        if (! $session) {
            return null;
        }

        $shopName = $order->shop?->name ?? 'Our Store';
        $orderRef = $order->order_number ?? $order->platform_order_id ?? $order->id;
        $currency = $order->currency ?? $order->shop?->currency ?? '';
        $total = number_format((float) ($order->total ?? 0), 2);
        $totalLine = trim($currency . ' ' . $total);

        // TODO: make working hours + payment details dynamic per shop (e.g. shop settings table).
        $shopOpenHour = 8;   // 08:00
        $shopCloseHour = 17; // 17:00
        $mpesaTill = '577 42 79';

        $now = now($order->shop?->timezone ?? config('app.timezone'));
        $isWithinHours = $now->hour >= $shopOpenHour && $now->hour < $shopCloseHour;

        $processingLine = $isWithinHours
            ? "We've received your order and will process it shortly. Thank you! 🙏"
            : "We've received your order. We will process it first thing in the morning. Thank you";

        $text = "Order Received - {$orderRef}*\n"
            . "_{$shopName}_\n\n"
            . "Total: {$totalLine}\n\n"
            /*  . "📍 *Delivery location*\nPlease reply with your delivery address (and a landmark if possible) so we can confirm and arrange delivery.\n\n" */
            . "💳 *Payment*\nM-Pesa Till: *{$mpesaTill}*\nAmount: *{$totalLine}*\nUse order *{$orderRef}* as the reference.\n\n"
            . $processingLine;

        return $this->sendMessage->execute(
            session: $session,
            toJid: $jid,
            text: $text,
            sentBy: null,
            customerId: $order->customer_id,
        );
    }
}
