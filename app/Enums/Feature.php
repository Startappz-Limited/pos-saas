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
}
