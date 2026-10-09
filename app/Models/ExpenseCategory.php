<?php

namespace App\Models;

use App\Enums\CategoryStatus;
use App\Enums\ExpenseCategoryType;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ExpenseCategory extends Model
{
    use BelongsToBusiness;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'parent_id',
        'depth',
        'path',
        'name',
        'slug',
        'code',
        'description',
        'type',
        'is_operational',
        'is_tax_deductible',
        'monthly_budget',
        'yearly_budget',
        'requires_approval',
        'approval_threshold',
        'icon',
        'color',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_operational' => 'boolean',
            'is_tax_deductible' => 'boolean',
            'requires_approval' => 'boolean',
            'monthly_budget' => 'decimal:2',
            'yearly_budget' => 'decimal:2',
            'approval_threshold' => 'decimal:2',
            'type' => ExpenseCategoryType::class,
            'status' => CategoryStatus::class,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
            if (empty($model->code)) {
                $model->code = 'CAT-'.strtoupper(Str::random(6));
            }
            if (empty($model->status)) {
                $model->status = CategoryStatus::ACTIVE;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // Relationships
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class, 'parent_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', CategoryStatus::ACTIVE);
    }

    public function scopeRootCategories($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOfType($query, ExpenseCategoryType $type)
    {
        return $query->where('type', $type);
    }

    // Methods
    public function isActive(): bool
    {
        return $this->status === CategoryStatus::ACTIVE;
    }

    public function getTotalExpenses($startDate = null, $endDate = null): float
    {
        $query = $this->expenses();

        if ($startDate && $endDate) {
            $query->whereBetween('expense_date', [$startDate, $endDate]);
        }

        return $query->sum('amount');
    }

    public function getBudgetUsagePercent($period = 'monthly'): ?float
    {
        $budget = $period === 'monthly' ? $this->monthly_budget : $this->yearly_budget;

        if (! $budget || $budget <= 0) {
            return null;
        }

        $startDate = $period === 'monthly' ? now()->startOfMonth() : now()->startOfYear();
        $endDate = $period === 'monthly' ? now()->endOfMonth() : now()->endOfYear();

        $spent = $this->getTotalExpenses($startDate, $endDate);

        return round(($spent / $budget) * 100, 2);
    }
}
