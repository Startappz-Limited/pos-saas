<?php

namespace App\Models;

use App\Casts\Quantity;
use App\Enums\TaxClass;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'sale_id',
        'product_id',
        'shop_id',
        'variation_id',
        'unit_id',
        'quantity',
        'base_quantity',
        'unit_price',
        'discount_amount',
        'line_total',
        'tax_class',
        'tax_rate',
        'tax_amount',
        'taxable_amount',
        'unit_cost',
        'total_cost',
        'profit',
        'profit_margin',
        // Every unit costed from a batch with a cost, or a known estimate (stage 10, step 6).
        'cost_known',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'cost_known' => 'boolean',
            'tax_class' => TaxClass::class,
            'quantity' => Quantity::class,
            'base_quantity' => Quantity::class,
            'unit_cost' => Quantity::class.':4',
            'tax_rate' => 'decimal:3',
            'tax_amount' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // A line with no base quantity given is the base unit (stage 11, step 3):
        // its quantity already is the base quantity.
        static::creating(function (self $item): void {
            if ($item->base_quantity === null && $item->quantity !== null) {
                $item->base_quantity = $item->quantity;
            }
        });
    }

    public function sale(): BelongsTo
    {
        // A deleted sale still resolves (stage 10, step 5).
        return $this->belongsTo(Sale::class)->withTrashed();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'variation_id');
    }

    /** The unit this line was sold in, soft-deleted ones included. */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id')->withTrashed();
    }

    /**
     * The decimal places a quantity of this line may use: the sold unit's precision (stage 11), else
     * the base unit's, else 3 (the stock's own scale, so a legacy line never refuses a fraction).
     */
    public function quantityPrecision(): int
    {
        if ($this->unit !== null) {
            return (int) $this->unit->precision;
        }

        $variation = $this->variation ?? Product::query()->find($this->product_id)?->defaultVariation()->first();

        return (int) ($variation?->baseUnit?->precision ?? 3);
    }

    /**
     * The HTML `step` for a quantity input of this line, so a browser refuses what the server would
     * ("1" for whole units, "0.001" for three decimals).
     */
    public function quantityStep(): string
    {
        $precision = $this->quantityPrecision();

        return $precision === 0 ? '1' : '0.'.str_repeat('0', $precision - 1).'1';
    }
}
