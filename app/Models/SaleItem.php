<?php

namespace App\Models;

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
        'quantity',
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
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tax_class' => TaxClass::class,
            'tax_rate' => 'decimal:3',
            'tax_amount' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
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
}
