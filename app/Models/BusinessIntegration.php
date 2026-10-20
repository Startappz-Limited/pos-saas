<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A business's own credentials for an outside service (stage 3): SMS,
 * WhatsApp Cloud, or one shop's M-Pesa account. The credentials are stored
 * encrypted, never shown again, and never exported or logged.
 *
 * Not audited on purpose: an audit row would carry the values.
 */
class BusinessIntegration extends Model
{
    use BelongsToBusiness;
    use BelongsToShop;

    /** A business-wide integration (SMS, WhatsApp Cloud) has no shop. */
    protected static bool $shopOptional = true;

    public const TYPE_SMS = 'sms';

    public const TYPE_WHATSAPP_CLOUD = 'whatsapp_cloud';

    public const TYPE_MPESA = 'mpesa';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'business_id',
        'shop_id',
        'type',
        'credentials',
        'status',
        'verified_at',
        'last_error',
        'created_by',
        'updated_by',
    ];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $integration): void {
            if (empty($integration->uuid)) {
                $integration->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * The usable integration of $type for $shop, else the business-wide one.
     */
    public static function forShop(string $type, ?int $shopId = null): ?self
    {
        return static::query()->where('type', $type)->where('status', self::STATUS_ACTIVE)
            ->where(fn ($query) => $query->where('shop_id', $shopId)->orWhereNull('shop_id'))
            ->orderByRaw('shop_id is null')
            ->first();
    }
}
