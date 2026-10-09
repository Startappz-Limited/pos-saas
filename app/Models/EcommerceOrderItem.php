<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceOrderItem extends Model
{
    protected $fillable = [
        'ecommerce_order_id',
        'platform_product_id',
        'platform_line_item_id',
        'product_id',
        'name',
        'sku',
        'quantity',
        'unit_price',
        'subtotal',
        'total',
        'tax_total',
        'platform_data',
    ];

    protected function casts(): array
    {
        return [
            'platform_data' => 'array',
        ];
    }

    public function ecommerceOrder(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function hasMatchedProduct(): bool
    {
        return $this->product_id !== null;
    }
}
