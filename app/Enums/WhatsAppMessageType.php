<?php

namespace App\Enums;

enum WhatsAppMessageType: string
{
    case Text = 'text';
    case Template = 'template';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Template => 'Template',
        };
    }
}
