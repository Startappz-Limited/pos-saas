<?php

namespace App\Enums;

enum BaileysMessageDirection: string
{
    case Outbound = 'outbound';
    case Inbound = 'inbound';

    public function label(): string
    {
        return match ($this) {
            self::Outbound => 'Outbound',
            self::Inbound => 'Inbound',
        };
    }
}
