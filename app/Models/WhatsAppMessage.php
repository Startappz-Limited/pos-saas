<?php

namespace App\Models;

use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'uuid',
        'shop_id',
        'customer_id',
        'sent_by',
        'phone',
        'direction',
        'message_type',
        'content',
        'template_name',
        'status',
        'whatsapp_message_id',
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
            'direction' => WhatsAppMessageDirection::class,
            'message_type' => WhatsAppMessageType::class,
            'status' => WhatsAppMessageStatus::class,
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * Mark the message as sent.
     */
    public function markAsSent(?string $whatsappMessageId = null): bool
    {
        return $this->update([
            'status' => WhatsAppMessageStatus::Sent,
            'whatsapp_message_id' => $whatsappMessageId,
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark the message as failed.
     */
    public function markAsFailed(string $errorMessage): bool
    {
        return $this->update([
            'status' => WhatsAppMessageStatus::Failed,
            'error_message' => $errorMessage,
            'failed_at' => now(),
        ]);
    }

    /**
     * Mark the message as delivered.
     */
    public function markAsDelivered(): bool
    {
        return $this->update([
            'status' => WhatsAppMessageStatus::Delivered,
            'delivered_at' => now(),
        ]);
    }

    /**
     * Mark the message as read.
     */
    public function markAsRead(): bool
    {
        return $this->update([
            'status' => WhatsAppMessageStatus::Read,
            'read_at' => now(),
        ]);
    }

    /**
     * Scope: Filter by shop.
     */
    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    /**
     * Scope: Filter by customer.
     */
    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope: Outbound messages only.
     */
    public function scopeOutbound($query)
    {
        return $query->where('direction', WhatsAppMessageDirection::Outbound);
    }

    /**
     * Scope: Failed messages only.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', WhatsAppMessageStatus::Failed);
    }
}
