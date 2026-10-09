<?php

namespace App\Models;

use App\Enums\AlertCategory;
use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Alert extends Model
{
    protected $fillable = [
        'uuid',
        'shop_id',
        'title',
        'message',
        'type',
        'severity',
        'category',
        'alertable_type',
        'alertable_id',
        'is_read',
        'is_resolved',
        'read_at',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
        'scheduled_at',
        'sent_at',
        'expires_at',
        'data',
        'actions',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => AlertType::class,
            'severity' => AlertSeverity::class,
            'category' => AlertCategory::class,
            'is_read' => 'boolean',
            'is_resolved' => 'boolean',
            'read_at' => 'datetime',
            'resolved_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'data' => 'array',
            'actions' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // Relationships

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function alertable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // Scopes

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    public function scopeOfType($query, AlertType $type)
    {
        return $query->where('type', $type->value);
    }

    public function scopeOfCategory($query, AlertCategory $category)
    {
        return $query->where('category', $category->value);
    }

    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    /**
     * Restrict to alerts the user may see. Mirrors Sale::scopeVisibleTo(), but
     * `shop_id` is nullable here — those are system-wide alerts and stay visible
     * to everyone.
     */
    public function scopeVisibleTo($query, User $user)
    {
        if (! $user->hasShopRestrictions()) {
            return $query;
        }

        return $query->where(function ($q) use ($user): void {
            $q->whereNull($this->qualifyColumn('shop_id'))
                ->orWhereIn($this->qualifyColumn('shop_id'), $user->assignedShopIds());
        });
    }

    public function scopeReminders($query)
    {
        return $query->where('type', AlertType::ORDER_REMINDER->value);
    }

    public function scopeNotes($query)
    {
        return $query->where('type', AlertType::ORDER_NOTE->value);
    }

    public function scopeUpcoming($query)
    {
        return $query->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now())
            ->where('is_resolved', false);
    }

    public function scopeOverdue($query)
    {
        return $query->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', now())
            ->where('is_resolved', false);
    }

    public function scopeScheduledBetween($query, $start, $end)
    {
        return $query->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$start, $end]);
    }

    // Methods

    public function markRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public function resolve(?string $notes = null, ?int $userId = null): void
    {
        $this->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => $userId ?? auth()->id(),
            'resolution_notes' => $notes,
        ]);
    }

    public function isReminder(): bool
    {
        return $this->type === AlertType::ORDER_REMINDER;
    }

    public function isNote(): bool
    {
        return $this->type === AlertType::ORDER_NOTE;
    }

    public function isOverdue(): bool
    {
        return $this->scheduled_at && $this->scheduled_at->isPast() && ! $this->is_resolved;
    }
}
