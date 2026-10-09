<?php

namespace App\Models;

use App\Enums\BaileysMessageDirection;
use App\Enums\BaileysMessageStatus;
use App\Enums\BaileysMessageType;
use App\Models\Concerns\BelongsToAccessibleShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BaileysMessage extends Model
{
    use BelongsToAccessibleShop;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'baileys_session_id',
        'shop_id',
        'baileys_chat_id',
        'customer_id',
        'sent_by',
        'chat_jid',
        'sender_jid',
        'wa_message_id',
        'direction',
        'type',
        'status',
        'content',
        'media_url',
        'media_mime',
        'media_filename',
        'media_size',
        'payload',
        'error_message',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => BaileysMessageDirection::class,
            'type' => BaileysMessageType::class,
            'status' => BaileysMessageStatus::class,
            'payload' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
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

    public function session(): BelongsTo
    {
        return $this->belongsTo(BaileysSession::class, 'baileys_session_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(BaileysChat::class, 'baileys_chat_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function markAsSent(?string $waMessageId = null): bool
    {
        return $this->update([
            'status' => BaileysMessageStatus::Sent,
            'wa_message_id' => $waMessageId ?? $this->wa_message_id,
            'sent_at' => now(),
        ]);
    }

    public function markAsDelivered(): bool
    {
        return $this->update([
            'status' => BaileysMessageStatus::Delivered,
            'delivered_at' => now(),
        ]);
    }

    public function markAsRead(): bool
    {
        return $this->update([
            'status' => BaileysMessageStatus::Read,
            'read_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): bool
    {
        return $this->update([
            'status' => BaileysMessageStatus::Failed,
            'error_message' => $error,
            'failed_at' => now(),
        ]);
    }

    /**
     * @param  Builder<BaileysMessage>  $query
     */
    public function scopeInbound(Builder $query): Builder
    {
        return $query->where('direction', BaileysMessageDirection::Inbound->value);
    }
}
