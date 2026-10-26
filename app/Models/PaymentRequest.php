<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An M-Pesa payment at a till (Phase 7): an STK Push the till asked for, or a
 * code a cashier entered offline. Confirmed only by querying the M-Pesa API
 * with the shop's own account; applied to its sale once. Audited.
 */
class PaymentRequest extends Model
{
    use Auditable;
    use BelongsToBusiness;
    use BelongsToShop;

    public const METHOD_STK = 'stk';

    public const METHOD_CODE = 'code';

    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    public const FAILED = 'failed';

    public const UNMATCHED = 'unmatched';

    protected $fillable = [
        'uuid', 'client_uuid', 'business_id', 'shop_id', 'register_id', 'requested_by', 'method', 'sale_client_uuid',
        'sale_id', 'phone', 'amount', 'status', 'shortcode', 'checkout_request_id', 'merchant_request_id', 'mpesa_receipt',
        'paid_amount', 'confirmed_at', 'sale_payment_id', 'applied_at', 'failure_reason', 'checks', 'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'applied_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'checks' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            $request->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /**
     * As the till receives it, in the pull and the M-Pesa endpoints.
     *
     * @return array<string, mixed>
     */
    public function present(): array
    {
        return [
            'uuid' => $this->uuid,
            'client_uuid' => $this->client_uuid,
            'method' => $this->method,
            'sale_client_uuid' => $this->sale_client_uuid,
            'status' => $this->status,
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'paid_amount' => $this->paid_amount === null ? null : number_format((float) $this->paid_amount, 2, '.', ''),
            'mpesa_receipt' => $this->mpesa_receipt,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'applied' => $this->applied_at !== null,
            'failure_reason' => $this->failure_reason,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
