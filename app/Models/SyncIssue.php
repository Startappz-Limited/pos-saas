<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Something a till's push turned up for an owner or manager (Phase 7,
 * decision 4.9). Recorded by App\Support\Sync\SyncIssues; resolving one is
 * audited. A manager sees the issues of the shops they work in.
 */
class SyncIssue extends Model
{
    use Auditable;
    use BelongsToBusiness;
    use BelongsToShop;

    /** An issue may name no shop; it stays within the business. */
    protected static bool $shopOptional = true;

    public const INVOICE_GAP = 'invoice_gap';

    public const INVOICE_REPEAT = 'invoice_repeat';

    public const POSSIBLE_DUPLICATE_CUSTOMER = 'possible_duplicate_customer';

    public const NEGATIVE_STOCK = 'negative_stock';

    public const IMPLAUSIBLE_TIME = 'implausible_time';

    protected $fillable = ['uuid', 'business_id', 'shop_id', 'register_id', 'type', 'key', 'details', 'resolved_at', 'resolved_by', 'resolution'];

    protected function casts(): array
    {
        return ['details' => 'array', 'resolved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $issue): void {
            $issue->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * What the issue is, for the review screen.
     */
    public function label(): string
    {
        return match ($this->type) {
            self::INVOICE_GAP => __('Missing invoice numbers'),
            self::INVOICE_REPEAT => __('Invoice number used twice'),
            self::POSSIBLE_DUPLICATE_CUSTOMER => __('Possible duplicate customer'),
            self::NEGATIVE_STOCK => __('Sold below zero stock'),
            self::IMPLAUSIBLE_TIME => __('Sale time in the future'),
            default => $this->type,
        };
    }

    /**
     * The badge colour for its type: a repeat is the most serious.
     */
    public function badgeVariant(): string
    {
        return match ($this->type) {
            self::INVOICE_REPEAT => 'danger',
            self::INVOICE_GAP, self::NEGATIVE_STOCK => 'warning',
            self::POSSIBLE_DUPLICATE_CUSTOMER => 'info',
            default => 'secondary',
        };
    }
}
