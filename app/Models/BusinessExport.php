<?php

namespace App\Models;

use App\Enums\BusinessExportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A ZIP of everything a business holds, built by BusinessExporter and kept on
 * the private disk until it expires. It holds every customer's details, so
 * only the business owner can download it.
 */
class BusinessExport extends Model
{
    use HasFactory;

    /**
     * Disk the ZIP files live on (storage/app/private, never public).
     */
    public const DISK = 'local';

    protected $fillable = [
        'uuid',
        'business_id',
        'requested_by',
        'status',
        'is_final',
        'path',
        'size',
        'error',
        'completed_at',
        'downloaded_at',
        'expires_at',
    ];

    protected $casts = [
        'status' => BusinessExportStatus::class,
        'is_final' => 'boolean',
        'size' => 'integer',
        'completed_at' => 'datetime',
        'downloaded_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $export): void {
            if (empty($export->uuid)) {
                $export->uuid = (string) Str::uuid();
            }
        });

        // The file holds personal data: it goes with the record
        static::deleting(function (self $export): void {
            $export->deleteFile();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isReady(): bool
    {
        return $this->status === BusinessExportStatus::READY
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isPending(): bool
    {
        return $this->status === BusinessExportStatus::PENDING;
    }

    public function wasDownloaded(): bool
    {
        return $this->downloaded_at !== null;
    }

    /**
     * File name offered to the browser.
     */
    public function downloadName(): string
    {
        $name = Str::slug($this->business?->name ?? 'business') ?: 'business';

        return "{$name}-export-{$this->completed_at?->format('Y-m-d')}.zip";
    }

    public function deleteFile(): void
    {
        if ($this->path) {
            Storage::disk(self::DISK)->delete($this->path);
        }
    }

    /**
     * Ready exports whose time is up.
     *
     * @param  Builder<self>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->where('status', BusinessExportStatus::READY)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }
}
