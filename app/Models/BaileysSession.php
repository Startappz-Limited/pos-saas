<?php

namespace App\Models;

use App\Enums\BaileysSessionStatus;
use App\Models\Concerns\BelongsToAccessibleShop;
use App\Services\BaileysMediaService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BaileysSession extends Model
{
    use BelongsToAccessibleShop;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'shop_id',
        'created_by',
        'name',
        'session_key',
        'jid',
        'phone_number',
        'display_name',
        'status',
        'qr_code',
        'qr_expires_at',
        'device_info',
        'last_error',
        'connected_at',
        'disconnected_at',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BaileysSessionStatus::class,
            'device_info' => 'array',
            'qr_expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'disconnected_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->session_key)) {
                $model->session_key = 'shop-'.($model->shop_id ?? 'x').'-'.Str::random(10);
            }
        });

        // Messages cascade-delete with the session; their media files did not,
        // and were left orphaned on disk forever.
        static::deleting(function (self $model): void {
            app(BaileysMediaService::class)->deleteSessionMedia($model);
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<BaileysChat, $this>
     */
    public function chats(): HasMany
    {
        return $this->hasMany(BaileysChat::class);
    }

    /**
     * @return HasMany<BaileysMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(BaileysMessage::class);
    }

    /**
     * @return HasMany<BaileysContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(BaileysContact::class);
    }

    public function isConnected(): bool
    {
        return $this->status === BaileysSessionStatus::Connected;
    }

    public function markConnected(
        ?string $jid = null,
        ?string $phoneNumber = null,
        ?string $displayName = null,
        ?CarbonInterface $at = null,
    ): bool {
        return $this->update([
            'status' => BaileysSessionStatus::Connected,
            'jid' => $jid ?? $this->jid,
            'phone_number' => $phoneNumber ?? $this->phone_number,
            'display_name' => $displayName ?? $this->display_name,
            'qr_code' => null,
            'qr_expires_at' => null,
            'last_error' => null,
            'connected_at' => $at ?? now(),
            'last_seen_at' => now(),
        ]);
    }

    public function markDisconnected(?string $reason = null, ?CarbonInterface $at = null): bool
    {
        return $this->update([
            'status' => BaileysSessionStatus::Disconnected,
            'disconnected_at' => $at ?? now(),
            'last_error' => $reason,
        ]);
    }

    /**
     * The connection dropped and the gateway is reconnecting by itself: no new
     * QR is needed, and a `session.connected` normally follows within seconds.
     */
    public function markReconnecting(?CarbonInterface $at = null): bool
    {
        return $this->update([
            'status' => BaileysSessionStatus::Connecting,
            'disconnected_at' => $at ?? now(),
        ]);
    }

    /**
     * Whether a gateway session event from this moment predates the last state
     * change already recorded. Gateway webhooks aren't ordered, so a reconnect
     * can land before the disconnect it follows; applying that late disconnect
     * would show a live session as down until it next reconnects. An event
     * without a time is applied, as before.
     */
    public function isStaleGatewayEvent(?CarbonInterface $occurredAt): bool
    {
        if ($occurredAt === null) {
            return false;
        }

        $latest = collect([$this->connected_at, $this->disconnected_at])->filter()->max();

        return $latest !== null && $occurredAt->lt($latest);
    }

    /**
     * @param  Builder<BaileysSession>  $query
     */
    public function scopeForShop(Builder $query, int $shopId): Builder
    {
        return $query->where('shop_id', $shopId);
    }

    /**
     * @param  Builder<BaileysSession>  $query
     */
    public function scopeConnected(Builder $query): Builder
    {
        return $query->where('status', BaileysSessionStatus::Connected->value);
    }
}
