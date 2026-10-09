<?php

namespace App\Enums;

enum BaileysMessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
    case Sticker = 'sticker';
    case Location = 'location';
    case Contact = 'contact';
    case Status = 'status';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Image => 'Image',
            self::Video => 'Video',
            self::Audio => 'Audio',
            self::Document => 'Document',
            self::Sticker => 'Sticker',
            self::Location => 'Location',
            self::Contact => 'Contact',
            self::Status => 'Status',
        };
    }
}
