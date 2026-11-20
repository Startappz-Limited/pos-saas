<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A product barcode that has no unit yet (stage 11, step 7): a product with real variations
 * carried one barcode for the whole product, and it waits here until an owner assigns it to one
 * of the product's units or drops it. Nothing sends it until then.
 */
class UnassignedBarcode extends Model
{
    use Auditable;
    use BelongsToBusiness;

    protected $fillable = ['uuid', 'business_id', 'product_id', 'barcode'];

    protected static function booted(): void
    {
        static::creating(function (self $row): void {
            $row->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
