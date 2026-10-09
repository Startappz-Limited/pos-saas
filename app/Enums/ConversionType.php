<?php

namespace App\Enums;

enum ConversionType: string
{
    case PURCHASE = 'purchase';
    case SIGNUP = 'signup';
    case LEAD = 'lead';
    case DOWNLOAD = 'download';
    case VISIT = 'visit';
    case CALL = 'call';

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE => 'Purchase',
            self::SIGNUP => 'Sign Up',
            self::LEAD => 'Lead Generated',
            self::DOWNLOAD => 'Download',
            self::VISIT => 'Store Visit',
            self::CALL => 'Phone Call',
        };
    }
}
