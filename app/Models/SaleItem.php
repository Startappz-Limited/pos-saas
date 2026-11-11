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
}
