<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A shop's price override for one unit (stage 11, step 2) — the only place a
 * shop overrides a price. Written here by the base unit's copy (step 7);
 * stage 11b adds editing every unit's shop price.
 */
class ShopUnitPrice extends Model
{
    use BelongsToShop;
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'unit_id',
        'selling_price',
        'wholesale_price',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id');
    }
}
