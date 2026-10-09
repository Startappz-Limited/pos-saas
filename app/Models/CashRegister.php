<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToAccessibleShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CashRegister extends Model
{
    use Auditable;
    use BelongsToAccessibleShop;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'shop_id',
        'user_id',
        'register_number',
        'register_date',
        'opening_balance',
        'expense_opening_balance',
        'expense_balance_used',
        'closing_balance',
        'expected_balance',
        'variance',
        'total_sales',
        'total_cash_sales',
        'total_card_sales',
        'total_credit_sales',
        'total_mobile_money_sales',
        'total_bank_transfer_sales',
        'total_cheque_sales',
        'transaction_count',
        'status',
        'opened_at',
        'closed_at',
        'closed_by',
        'opening_notes',
        'closing_notes',
    ];

    protected $casts = [
        'register_date' => 'date',
        'opening_balance' => 'decimal:2',
        'expense_opening_balance' => 'decimal:2',
        'expense_balance_used' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'expected_balance' => 'decimal:2',
        'variance' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'total_cash_sales' => 'decimal:2',
        'total_card_sales' => 'decimal:2',
        'total_credit_sales' => 'decimal:2',
        'total_mobile_money_sales' => 'decimal:2',
        'total_bank_transfer_sales' => 'decimal:2',
        'total_cheque_sales' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->opened_at)) {
                $model->opened_at = now();
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'register_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function getRemainingExpenseBalance(): float
    {
        return (float) $this->expense_opening_balance - (float) $this->expense_balance_used;
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('register_date', $date);
    }

    public function scopeForShop($query, $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    // Methods
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function calculateExpectedBalance(): float
    {
        return $this->opening_balance + $this->total_cash_sales;
    }

    public function calculateVariance(): float
    {
        if ($this->closing_balance === null) {
            return 0;
        }

        return $this->closing_balance - $this->calculateExpectedBalance();
    }

    public function updateSalesTotals(): void
    {
        $sales = $this->sales()->where('status', 'completed')->get();

        $this->update([
            'total_sales' => $sales->sum('total_amount'),
            'total_cash_sales' => $sales->where('payment_method', 'cash')->sum('total_amount'),
            'total_card_sales' => $sales->where('payment_method', 'card')->sum('total_amount'),
            'total_credit_sales' => $sales->where('payment_method', 'credit')->sum('total_amount'),
            'total_mobile_money_sales' => $sales->where('payment_method', 'mobile_money')->sum('total_amount'),
            'total_bank_transfer_sales' => $sales->where('payment_method', 'bank_transfer')->sum('total_amount'),
            'total_cheque_sales' => $sales->where('payment_method', 'cheque')->sum('total_amount'),
            'transaction_count' => $sales->count(),
        ]);
    }

    /**
     * Get the active register for a shop
     */
    public static function getActiveRegister($shopId = null): ?self
    {
        $shopId = $shopId ?? auth()->user()->shop_id ?? Shop::first()?->id;

        return self::where('shop_id', $shopId)
            ->where('status', 'open')
            ->whereDate('register_date', today())
            ->first();
    }

    /**
     * Open a new register for today
     */
    public static function openRegister($shopId = null, $openingBalance = 0, $notes = null, $expenseOpeningBalance = 0): self
    {
        $shopId = $shopId ?? auth()->user()->shop_id ?? Shop::first()?->id;
        $userId = auth()->id();

        // Close any open registers from previous days
        self::where('shop_id', $shopId)
            ->where('status', 'open')
            ->where('register_date', '<', today())
            ->each(fn ($register) => $register->closeRegister());

        $registerNumber = 'REG-'.strtoupper(Str::random(6));

        return self::create([
            'shop_id' => $shopId,
            'user_id' => $userId,
            'register_number' => $registerNumber,
            'register_date' => today(),
            'opening_balance' => $openingBalance,
            'expense_opening_balance' => $expenseOpeningBalance,
            'status' => 'open',
            'opened_at' => now(),
            'opening_notes' => $notes,
        ]);
    }

    /**
     * Close this register
     */
    public function closeRegister($closingBalance = null, $notes = null): bool
    {
        if ($this->isClosed()) {
            return false;
        }

        $this->updateSalesTotals();

        $expectedBalance = $this->calculateExpectedBalance();
        $actualClosingBalance = $closingBalance ?? $expectedBalance;

        $this->update([
            'closing_balance' => $actualClosingBalance,
            'expected_balance' => $expectedBalance,
            'variance' => $actualClosingBalance - $expectedBalance,
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by' => auth()->id(),
            'closing_notes' => $notes,
        ]);

        return true;
    }
}
