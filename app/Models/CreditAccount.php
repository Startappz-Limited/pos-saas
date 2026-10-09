<?php

namespace App\Models;

use App\Enums\CreditAccountStatus;
use App\Enums\CreditTransactionType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToAccessibleShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CreditAccount extends Model
{
    use Auditable;
    use BelongsToAccessibleShop;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'customer_id',
        'shop_id',
        'credit_limit',
        'current_balance',
        'available_credit',
        'status',
        'is_suspended',
        'suspension_reason',
        'payment_terms_days',
        'grace_period_days',
        'limit_updated_at',
        'limit_updated_by',
        'last_purchase_at',
        'last_payment_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => CreditAccountStatus::class,
            'credit_limit' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'available_credit' => 'decimal:2',
            'is_suspended' => 'boolean',
            'payment_terms_days' => 'integer',
            'grace_period_days' => 'integer',
            'limit_updated_at' => 'datetime',
            'last_purchase_at' => 'datetime',
            'last_payment_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (CreditAccount $creditAccount): void {
            if (empty($creditAccount->uuid)) {
                $creditAccount->uuid = (string) Str::uuid();
            }

            if (empty($creditAccount->status)) {
                $creditAccount->status = CreditAccountStatus::ACTIVE;
            }

            $creditAccount->available_credit = $creditAccount->calculateAvailableCredit();

            if (Auth::check() && empty($creditAccount->created_by)) {
                $creditAccount->created_by = Auth::id();
            }
        });

        static::saving(function (CreditAccount $creditAccount): void {
            $creditAccount->available_credit = $creditAccount->calculateAvailableCredit();
        });

        static::updating(function (CreditAccount $creditAccount): void {
            if (Auth::check()) {
                $creditAccount->updated_by = Auth::id();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function limitRequests(): HasMany
    {
        return $this->hasMany(CreditLimitRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function limitUpdater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'limit_updated_by');
    }

    public function canMakePurchase(float $amount): bool
    {
        return $this->customer?->customer_type === 'wholesale'
            && $this->customer?->allow_credit === true
            && $this->status->canMakePurchases()
            && ! $this->is_suspended
            && (float) $this->available_credit >= $amount;
    }

    public function calculateAvailableCredit(): float
    {
        return max(0, (float) $this->credit_limit - (float) $this->current_balance);
    }

    public function utilizationPercentage(): float
    {
        if ((float) $this->credit_limit <= 0) {
            return 0;
        }

        return round(((float) $this->current_balance / (float) $this->credit_limit) * 100, 2);
    }

    public function suspend(string $reason): void
    {
        $this->update([
            'status' => CreditAccountStatus::SUSPENDED,
            'is_suspended' => true,
            'suspension_reason' => $reason,
        ]);
    }

    public function reactivate(): void
    {
        $this->update([
            'status' => CreditAccountStatus::ACTIVE,
            'is_suspended' => false,
            'suspension_reason' => null,
        ]);
    }

    public function overdueTransactions()
    {
        return $this->transactions()
            ->where('type', CreditTransactionType::PURCHASE)
            ->where('due_date', '<', today())
            ->orderBy('due_date');
    }

    /**
     * How much of the outstanding balance is past its due date.
     *
     * Payments settle the oldest invoice first (FIFO), and overdue invoices are
     * by definition the oldest — so every payment reduces overdue debt before it
     * touches anything current. Subtracting the full credit total is therefore
     * correct here, and matches how CreditAccountService buckets the aging
     * report. Do not "fix" this into a per-invoice allocation without changing
     * that report too, or the two will disagree.
     */
    public function overdueAmount(): float
    {
        $overdueDebits = (float) $this->overdueTransactions()->sum('debit');
        $credits = (float) $this->transactions()->sum('credit');

        return round(max(0, $overdueDebits - $credits), 2);
    }

    public function scopeActive($query)
    {
        return $query->where('status', CreditAccountStatus::ACTIVE);
    }

    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    /**
     * Restrict to the shops this user is assigned to.
     *
     * A user with no shop assignments is unrestricted; one assigned to several
     * shops sees all of them, not just their primary. Mirrors Sale::scopeVisibleTo.
     */
    public function scopeVisibleTo($query, User $user)
    {
        if (! $user->hasShopRestrictions()) {
            return $query;
        }

        return $query->whereIn($this->qualifyColumn('shop_id'), $user->accessibleShopIds());
    }

    public function scopeWithBalance($query)
    {
        return $query->where('current_balance', '>', 0);
    }

    public function scopeHighUtilization($query, float $threshold = 80)
    {
        return $query->where('credit_limit', '>', 0)
            ->whereRaw('(current_balance / credit_limit) * 100 >= ?', [$threshold]);
    }
}
