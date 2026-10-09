<?php

namespace App\Enums;

enum BaileysChatType: string
{
    case Private = 'private';
    case Group = 'group';
    case Channel = 'channel';
    case Broadcast = 'broadcast';
    case Status = 'status';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Private',
            self::Group => 'Group',
            self::Channel => 'Channel',
            self::Broadcast => 'Broadcast',
            self::Status => 'Status',
        };
    }

    public static function fromJid(string $jid): self
    {
        return match (true) {
            str_ends_with($jid, '@g.us') => self::Group,
            str_ends_with($jid, '@newsletter') => self::Channel,
            str_ends_with($jid, '@broadcast') => $jid === 'status@broadcast' ? self::Status : self::Broadcast,
            default => self::Private,
        };
    }
}
