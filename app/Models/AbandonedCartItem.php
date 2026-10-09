<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbandonedCartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'abandoned_cart_id',
        'platform_product_id',
        'platform_variation_id',
        'product_id',
        'variation_id',
        'sku',
        'name',
        'quantity',
        'unit_price',
        'line_total',
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function abandonedCart(): BelongsTo
    {
        return $this->belongsTo(AbandonedCart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'variation_id');
    }

    public function hasMatchedProduct(): bool
    {
        return $this->product_id !== null;
    }

    /**
     * Whether the line maps to something the till can sell: a product, and its
     * variation when the product has variations.
     */
    public function isSellable(): bool
    {
        if ($this->product_id === null) {
            return false;
        }

        $product = $this->relationLoaded('product') ? $this->product : $this->product()->first();

        if ($product === null) {
            return false;
        }

        return ! $product->has_variations || $this->variation_id !== null;
    }
}
