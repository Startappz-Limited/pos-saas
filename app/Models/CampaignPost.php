<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Enums\SocialPlatform;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CampaignPost extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'campaign_id',
        'shop_id',
        'social_account_id',
        'product_id',
        'platform',
        'status',
        'caption',
        'hashtags',
        'call_to_action',
        'media_urls',
        'landing_url',
        'ai_generated',
        'ai_provider',
        'ai_model',
        'ai_prompt_context',
        'scheduled_at',
        'published_at',
        'external_post_id',
        'external_post_url',
        'last_error',
        'attempts',
        'impressions',
        'reach',
        'clicks',
        'reactions',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
            'status' => PostStatus::class,
            'hashtags' => 'array',
            'media_urls' => 'array',
            'ai_generated' => 'boolean',
            'ai_prompt_context' => 'array',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'attempts' => 'integer',
            'impressions' => 'integer',
            'reach' => 'integer',
            'clicks' => 'integer',
            'reactions' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $post): void {
            if (empty($post->uuid)) {
                $post->uuid = (string) Str::uuid();
            }
            if (auth()->check() && empty($post->created_by)) {
                $post->created_by = auth()->id();
            }
        });
    }

    // Relationships

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes

    public function scopeDuePublishing(Builder $query): Builder
    {
        return $query->where('status', PostStatus::SCHEDULED)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());
    }

    public function scopeForCampaign(Builder $query, int $campaignId): Builder
    {
        return $query->where('campaign_id', $campaignId);
    }

    // Helpers

    public function isPublishable(): bool
    {
        return in_array($this->status, [PostStatus::DRAFT, PostStatus::SCHEDULED, PostStatus::FAILED], true);
    }

    public function fullText(): string
    {
        $parts = array_filter([
            $this->caption,
            $this->call_to_action,
            $this->landing_url,
            collect($this->hashtags ?? [])->map(fn($tag) => Str::startsWith($tag, '#') ? $tag : '#' . ltrim($tag, '#'))->implode(' '),
        ]);

        return implode("\n\n", $parts);
    }
}
