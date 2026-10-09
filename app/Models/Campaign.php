<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\MarketingChannel;
use App\Models\Concerns\BelongsToAccessibleShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Campaign extends Model
{
    use BelongsToAccessibleShop;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'shop_id',
        'name',
        'code',
        'description',
        'campaign_type',
        'channel',
        'budget',
        'spent',
        'currency',
        'start_date',
        'end_date',
        'target_revenue',
        'target_conversions',
        'target_reach',
        'actual_revenue',
        'conversions',
        'impressions',
        'clicks',
        'reach',
        'status',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'tracking_url',
        'promo_code',
        'auto_post_enabled',
        'ai_assist_enabled',
        'ai_settings',
        'content_template',
        'default_landing_url',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'campaign_type' => CampaignType::class,
            'channel' => MarketingChannel::class,
            'status' => CampaignStatus::class,
            'budget' => 'decimal:2',
            'spent' => 'decimal:2',
            'target_revenue' => 'decimal:2',
            'actual_revenue' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'auto_post_enabled' => 'boolean',
            'ai_assist_enabled' => 'boolean',
            'ai_settings' => 'array',
            'content_template' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $campaign): void {
            if (empty($campaign->uuid)) {
                $campaign->uuid = (string) Str::uuid();
            }
            if (empty($campaign->code)) {
                $campaign->code = 'CMP-'.strtoupper(Str::random(8));
            }
            if (auth()->check() && empty($campaign->created_by)) {
                $campaign->created_by = auth()->id();
            }
        });

        static::updating(function (self $campaign): void {
            if (auth()->check()) {
                $campaign->updated_by = auth()->id();
            }
        });
    }

    // Relationships

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(CampaignPost::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'campaign_product')
            ->withPivot(['promo_price', 'landing_url', 'display_order'])
            ->withTimestamps();
    }

    // Scopes

    public function scopeForShop(Builder $query, int $shopId): Builder
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CampaignStatus::ACTIVE);
    }

    public function scopeRunningOn(Builder $query, \DateTimeInterface $date): Builder
    {
        return $query->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date);
    }

    // Helpers

    public function isActive(): bool
    {
        return $this->status === CampaignStatus::ACTIVE;
    }

    public function isEditable(): bool
    {
        return $this->status?->isEditable() ?? true;
    }

    public function getRoiAttribute(): float
    {
        $spent = (float) ($this->spent ?? 0);
        if ($spent <= 0) {
            return 0.0;
        }

        return round((((float) $this->actual_revenue - $spent) / $spent) * 100, 2);
    }

    public function getCtrAttribute(): float
    {
        $impressions = (int) $this->impressions;
        if ($impressions <= 0) {
            return 0.0;
        }

        return round(((int) $this->clicks / $impressions) * 100, 2);
    }
}
