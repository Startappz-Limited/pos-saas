<?php

namespace App\Models;

use App\Enums\ExpenseStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Expense extends Model
{
    use Auditable;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'shop_id',
        'category_id',
        'vendor_id',
        'expense_number',
        'title',
        'description',
        'amount',
        'currency',
        'payment_method',
        'reference_number',
        'is_paid',
        'settled_from_register',
        'cash_register_id',
        'paid_date',
        'expense_date',
        'due_date',
        'status',
        'rejection_reason',
        'submitted_at',
        'approved_at',
        'approved_by',
        'is_recurring',
        'recurrence_frequency',
        'recurrence_end_date',
        'parent_expense_id',
        'is_tax_deductible',
        'tax_amount',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'is_paid' => 'boolean',
            'settled_from_register' => 'boolean',
            'is_recurring' => 'boolean',
            'is_tax_deductible' => 'boolean',
            'expense_date' => 'date',
            'due_date' => 'date',
            'paid_date' => 'date',
            'recurrence_end_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'status' => ExpenseStatus::class,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->expense_number)) {
                $model->expense_number = 'EXP-'.strtoupper(Str::random(8));
            }
            if (empty($model->status)) {
                $model->status = ExpenseStatus::DRAFT;
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'vendor_id');
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function parentExpense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'parent_expense_id');
    }

    public function childExpenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'parent_expense_id');
    }

    // Scopes
    public function scopeForShop($query, $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->hasShopRestrictions()) {
            return $query;
        }

        return $query->whereIn($this->qualifyColumn('shop_id'), $user->assignedShopIds());
    }

    public function scopeStatus($query, ExpenseStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', ExpenseStatus::PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', ExpenseStatus::APPROVED);
    }

    public function scopePaid($query)
    {
        return $query->where('is_paid', true);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('is_paid', false);
    }

    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate]);
    }

    // Methods
    public function submit(): void
    {
        $this->status = ExpenseStatus::PENDING;
        $this->submitted_at = now();
        $this->save();
    }

    public function approve(?int $approvedBy = null): void
    {
        $this->status = ExpenseStatus::APPROVED;
        $this->approved_at = now();
        $this->approved_by = $approvedBy ?? auth()->id();
        $this->save();
    }

    public function reject(string $reason): void
    {
        $this->status = ExpenseStatus::REJECTED;
        $this->rejection_reason = $reason;
        $this->save();
    }

    public function markPaid(?string $paymentMethod = null, ?string $reference = null): void
    {
        $this->status = ExpenseStatus::PAID;
        $this->is_paid = true;
        $this->paid_date = now();
        if ($paymentMethod) {
            $this->payment_method = $paymentMethod;
        }
        if ($reference) {
            $this->reference_number = $reference;
        }
        $this->save();
    }

    public function cancel(): void
    {
        $this->status = ExpenseStatus::CANCELLED;
        $this->save();
    }

    public function canEdit(): bool
    {
        return $this->status->canEdit();
    }

    public function canApprove(): bool
    {
        return $this->status->canApprove();
    }

    public function canPay(): bool
    {
        return $this->status->canPay();
    }

    public function requiresApproval(): bool
    {
        if (! $this->category) {
            return false;
        }

        return $this->category->requires_approval ||
            ($this->category->approval_threshold && $this->amount > $this->category->approval_threshold);
    }
}
