<?php

namespace App\Actions;

use App\Actions\Baileys\SendBaileysMessage;
use App\Enums\AbandonedCartStatus;
use App\Enums\AlertSeverity;
use App\Enums\BaileysMessageStatus;
use App\Exceptions\AbandonedCartActionException;
use App\Models\AbandonedCart;
use App\Models\AbandonedCartItem;
use App\Models\BaileysMessage;
use App\Models\BaileysSession;
use App\Models\User;
use App\Support\BaileysJid;
use App\Support\PhoneNumber;

/**
 * Sends the customer a WhatsApp nudge about their abandoned cart through the
 * shop's Baileys session — Baileys, not the Cloud API, because this is a cold
 * contact and the Cloud API can only message inside a 24-hour customer window.
 */
class SendAbandonedCartWhatsApp
{
    public function __construct(private readonly SendBaileysMessage $sendMessage) {}

    /**
     * @throws AbandonedCartActionException when the message cannot be sent.
     */
    public function execute(AbandonedCart $cart, User $user, ?string $customMessage = null): BaileysMessage
    {
        $cart->loadMissing(['shop', 'items', 'customer']);

        if (in_array($cart->status, [AbandonedCartStatus::Converted, AbandonedCartStatus::Recovered], true)) {
            throw new AbandonedCartActionException('This cart has already been recovered; there is nothing to remind the customer about.');
        }

        if ($cart->opted_out_at !== null) {
            throw new AbandonedCartActionException('The customer has opted out of cart reminders.');
        }

        $phone = $cart->customer_phone ?: $cart->customer?->phone;

        if (! $phone || PhoneNumber::normalize($phone) === null) {
            throw new AbandonedCartActionException('This cart has no usable phone number to send a WhatsApp message to.');
        }

        $jid = BaileysJid::fromPhone(PhoneNumber::normalize($phone));

        $session = BaileysSession::query()
            ->where('shop_id', $cart->shop_id)
            ->connected()
            ->latest('connected_at')
            ->first();

        if (! $jid || ! $session) {
            throw new AbandonedCartActionException('No WhatsApp (Baileys) session is connected for this shop.');
        }

        $customMessage = $customMessage !== null ? trim($customMessage) : null;

        $message = $this->sendMessage->execute(
            session: $session,
            toJid: $jid,
            text: $this->compose($cart, $customMessage ?: null),
            sentBy: $user->id,
            customerId: $cart->customer_id,
        );

        if ($message->status === BaileysMessageStatus::Failed) {
            $cart->logActivity(
                'WhatsApp reminder failed',
                (string) ($message->error_message ?: 'The WhatsApp bridge rejected the message.'),
                ['event' => 'whatsapp_failed', 'baileys_message_id' => $message->id],
                severity: AlertSeverity::MEDIUM,
                userId: $user->id,
            );

            throw new AbandonedCartActionException('The WhatsApp message could not be sent. Check the WhatsApp session and try again.');
        }

        $cart->forceFill([
            'status' => $cart->status === AbandonedCartStatus::Lost || $cart->status === AbandonedCartStatus::Abandoned
                ? AbandonedCartStatus::Contacted
                : $cart->status,
            'last_contacted_at' => now(),
            'last_activity' => 'pos_whatsapp_sent',
        ])->save();

        // The timeline records THAT we wrote, not the text: the text carries the
        // live recovery link.
        $cart->logActivity(
            "WhatsApp reminder sent by {$user->name}",
            $customMessage ? 'Custom message' : 'Standard cart reminder',
            ['event' => 'pos_whatsapp_sent', 'baileys_message_id' => $message->id],
            userId: $user->id,
        );

        return $message;
    }

    /**
     * The message: greeting, what they left, the total, and the recovery link.
     */
    public function compose(AbandonedCart $cart, ?string $customMessage = null): string
    {
        $firstName = trim(strtok((string) $cart->customer_name, ' ') ?: '');
        $shopName = $cart->shop?->name ?? 'our shop';
        $link = $cart->checkout_link;

        if ($customMessage !== null) {
            return $link && ! str_contains($customMessage, $link)
                ? $customMessage."\n\n".$link
                : $customMessage;
        }

        $lines = $cart->items->map(fn (AbandonedCartItem $item): string => "• {$item->name} x{$item->quantity} — "
            .$cart->currency.' '.number_format((float) $item->line_total, 2));

        $text = ($firstName !== '' ? "Hi {$firstName}," : 'Hi,')."\n\n"
            ."You left these in your cart at *{$shopName}*:\n"
            .$lines->implode("\n")."\n\n"
            .'*Total: '.$cart->currency.' '.number_format((float) $cart->total, 2)."*\n\n";

        $text .= $link
            ? "Complete your order here:\n{$link}\n\n"
            : '';

        return $text.'Reply to this message if you have any questions — we are happy to help.';
    }
}
