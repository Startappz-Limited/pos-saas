<?php

namespace App\Enums;

/**
 * What a plan can switch on (Phase 4, decision 4.6). The set a plan holds is
 * data (`plans.features`); the placeholders live in the plans migration.
 */
enum Feature: string
{
    case Pos = 'pos';
    case Inventory = 'inventory';
    case Reports = 'reports';
    case EcommerceSync = 'ecommerce_sync';
    case Whatsapp = 'whatsapp';
    case Ai = 'ai';
    case Marketing = 'marketing';
    case Api = 'api';

    /**
     * The name a customer sees, as on the plan cards at sign-up.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pos => __('Point of sale'),
            self::Inventory => __('Stock and inventory'),
            self::Reports => __('Reports'),
            self::EcommerceSync => __('Online store sync (WooCommerce, Shopify)'),
            self::Whatsapp => __('WhatsApp receipts and messages'),
            self::Ai => __('AI assistant'),
            self::Marketing => __('Marketing and ads'),
            self::Api => __('API access'),
        };
    }
}
