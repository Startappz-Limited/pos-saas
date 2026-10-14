<?php

namespace App\Models;

use App\Enums\BaileysChatType;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BaileysChat extends Model
{
    use BelongsToShop;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'baileys_session_id',
        'shop_id',
        'jid',
        'phone',
        'name',
        'type',
        'unread_count',
        'last_message_at',
        'last_message_preview',
        'is_archived',
        'is_pinned',
        'is_muted',
        'metadata',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $chat): void {
            if (empty($chat->uuid)) {
                $chat->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Bound by uuid in routes, not by its sequential id (stage 1a).
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BaileysChatType::class,
            'last_message_at' => 'datetime',
            'is_archived' => 'boolean',
            'is_pinned' => 'boolean',
            'is_muted' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(BaileysSession::class, 'baileys_session_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return HasMany<BaileysMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(BaileysMessage::class);
    }

    /**
     * @param  Builder<BaileysChat>  $query
     */
    public function scopeOfType(Builder $query, BaileysChatType $type): Builder
    {
        return $query->where('type', $type->value);
    }
}
