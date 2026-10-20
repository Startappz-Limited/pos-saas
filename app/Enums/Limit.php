<?php

namespace App\Enums;

/**
 * The quantities a plan caps (Phase 4, decision 4.6). `plans.limits` holds one
 * entry per case except Shops, which is what the subscription pays for;
 * a null entry means unlimited. Registers are counted per shop.
 */
enum Limit: string
{
    case Shops = 'shops';
    case Staff = 'staff';
    case Products = 'products';
    case Registers = 'registers';
    case WhatsappSessions = 'whatsapp_sessions';

    public function label(): string
    {
        return match ($this) {
            self::Shops => __('shops'),
            self::Staff => __('staff'),
            self::Products => __('products'),
            self::Registers => __('registers per shop'),
            self::WhatsappSessions => __('WhatsApp sessions'),
        };
    }
}
