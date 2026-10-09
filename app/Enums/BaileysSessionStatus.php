<?php

namespace App\Enums;

enum BaileysSessionStatus: string
{
    case Pending = 'pending';
    case QrReady = 'qr_ready';
    case Connecting = 'connecting';
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::QrReady => 'Awaiting QR Scan',
            self::Connecting => 'Connecting',
            self::Connected => 'Connected',
            self::Disconnected => 'Disconnected',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending, self::Connecting => 'info',
            self::QrReady => 'warning',
            self::Connected => 'success',
            self::Disconnected => 'secondary',
            self::Failed => 'danger',
        };
    }

    /**
     * Map a Chatway Gateway session status onto ours. The gateway's
     * `disconnected` means the socket dropped and it is already reconnecting
     * by itself, so it is Connecting here; only `logged_out` needs a new QR.
     */
    public static function fromGateway(?string $status): self
    {
        return match ($status) {
            'qr_ready' => self::QrReady,
            'connected' => self::Connected,
            'disconnected' => self::Connecting,
            'logged_out' => self::Disconnected,
            default => self::Pending,
        };
    }
}
