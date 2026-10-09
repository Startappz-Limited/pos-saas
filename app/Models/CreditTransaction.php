<?php

namespace App\Models;

use App\Enums\CreditTransactionType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToAccessibleShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CreditTransaction extends Model
{
    use Auditable;
    use BelongsToAccessibleShop;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'credit_account_id',
        'customer_id',
        'shop_id',
        'transaction_number',
        'type',
        'reference_type',
        'reference_id',
        'debit',
        'credit',
        'balance_before',
        'balance_after',
        'due_date',
        'is_overdue',
        'days_overdue',
        'description',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => CreditTransactionType::class,
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'due_date' => 'date',
            'is_overdue' => 'boolean',
            'days_overdue' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (CreditTransaction $creditTransaction): void {
            if (empty($creditTransaction->uuid)) {
                $creditTransaction->uuid = (string) Str::uuid();
            }

            if (empty($creditTransaction->transaction_number)) {
                $creditTransaction->transaction_number = self::generateTransactionNumber();
            }

            if ($creditTransaction->due_date && $creditTransaction->due_date->isPast() && $creditTransaction->debit > 0) {
                $creditTransaction->is_overdue = true;
                $creditTransaction->days_overdue = (int) $creditTransaction->due_date->diffInDays(today());
            }

            if (Auth::check() && empty($creditTransaction->created_by)) {
                $creditTransaction->created_by = Auth::id();
            }
        });

        static::updating(function (CreditTransaction $creditTransaction): void {
            if (Auth::check()) {
                $creditTransaction->updated_by = Auth::id();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function generateTransactionNumber(): string
    {
        $date = now()->format('ymd');
        $sequence = self::whereDate('created_at', today())->count() + 1;

        return 'CTX-'.$date.'-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(CreditAccount::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo('reference', 'reference_type', 'reference_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getAmountAttribute(): float
    {
        return $this->type->isDebit() ? (float) $this->debit : (float) $this->credit;
    }

    public function scopeDebits($query)
    {
        return $query->where('debit', '>', 0);
    }

    public function scopeCredits($query)
    {
        return $query->where('credit', '>', 0);
    }

    public function scopeOverdue($query)
    {
        return $query->where('is_overdue', true)
            ->orWhere(function ($query): void {
                $query->where('debit', '>', 0)
                    ->whereDate('due_date', '<', today());
            });
    }

    public function scopeForAccount($query, int $creditAccountId)
    {
        return $query->where('credit_account_id', $creditAccountId);
    }

    /**
     * Restrict to the shops this user is assigned to.
     *
     * Mirrors CreditAccount::scopeVisibleTo — see the note there.
     */
    public function scopeVisibleTo($query, User $user)
    {
        if (! $user->hasShopRestrictions()) {
            return $query;
        }

        return $query->whereIn($this->qualifyColumn('shop_id'), $user->accessibleShopIds());
    }
}
