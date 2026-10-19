<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An archive of a business's data (stage 3): every row, audit entry and file,
 * built in the background (BuildBusinessExport) and downloaded by an owner.
 */
class BusinessExport extends Model
{
    use BelongsToBusiness;

    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const PURPOSE_REQUEST = 'request';

    public const PURPOSE_CLOSURE = 'closure';

    protected $fillable = [
        'business_id',
        'requested_by',
        'purpose',
        'status',
        'path',
        'size',
        'summary',
        'error',
        'completed_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $export): void {
            if (empty($export->uuid)) {
                $export->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * An export by uuid in whichever business it belongs to: its download
     * link works while the business is closing and can't be opened. The
     * caller checks the person is an owner of that business.
     */
    public static function findAcrossBusinesses(string $uuid): ?self
    {
        return static::query()->withoutGlobalScopes()->where('uuid', $uuid)->first();
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
