<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case EMAIL = 'email';
    case SMS = 'sms';
    case WHATSAPP = 'whatsapp';
    case IN_APP = 'in_app';
    case PUSH = 'push';
    case SLACK = 'slack';
    case WEBHOOK = 'webhook';

    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'Email',
            self::SMS => 'SMS',
            self::WHATSAPP => 'WhatsApp',
            self::IN_APP => 'In-App',
            self::PUSH => 'Push Notification',
            self::SLACK => 'Slack',
            self::WEBHOOK => 'Webhook',
        };
    }
}
