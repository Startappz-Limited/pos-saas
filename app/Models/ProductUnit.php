<?php

namespace App\Models;

use App\Casts\Quantity;
use App\Exceptions\BusinessRuleException;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * A unit a variation is held or sold in (stage 11, step 2): every variation
 * has exactly one base unit (factor 1, the stock's own precision), plus any
 * number of pack units ("1 Strip = 10 Tablet"; "1 Crate = 24 Bottle"). Stock
 * is always held in the base unit; a sale, return or (stage 12) GRN line
 * records the unit it was in and the base quantity that moves the ledger.
 *
 * Not RecordsSyncDeletion: a deleted unit stays on the till as an update, so
 * past receipts that named it still render (stage 11, step 8).
 */
class ProductUnit extends Model
{
    use Auditable;
    use BelongsToBusiness;
    use HasFactory, HasUuids;
    use SoftDeletes;

    /**
     * Tables whose lines name a unit (`unit_id`), checked by isUsed(). Starts
     * empty: nothing names a unit yet. Step 3 adds 'sale_items' and
     * 'return_items'; stage 12 adds GRN, purchase-order and transfer lines.
     */
    public const USED_ON = [];

    protected $fillable = [
        'uuid',
        'business_id',
        'variation_id',
        'name',
        'factor',
        'precision',
        'barcode',
        'selling_price',
        'wholesale_price',
        'is_base',
        'can_sell',
        'can_buy',
        'is_default_purchase',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'factor' => Quantity::class,
            'precision' => 'integer',
            'selling_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'is_base' => 'boolean',
            'can_sell' => 'boolean',
            'can_buy' => 'boolean',
            'is_default_purchase' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function (self $unit): void {
            if ($unit->isDirty('factor') && $unit->getOriginal('is_base')) {
                throw BusinessRuleException::because('The base unit\'s factor is always 1.', 'unit_is_base');
            }

            if ($unit->isDirty('factor') && $unit->isUsed()) {
                throw BusinessRuleException::because(
                    'This unit has been used on a line. To change the pack size, add a new unit and stop selling this one.',
                    'unit_in_use'
                );
            }

            if ($unit->isDirty('is_base') && ! $unit->is_base && $unit->getOriginal('is_base')) {
                throw BusinessRuleException::because('A variation must keep a base unit.', 'unit_is_base');
            }
        });

        static::deleting(function (self $unit): void {
            if ($unit->is_base) {
                throw BusinessRuleException::because('The base unit can\'t be removed.', 'unit_is_base');
            }
        });
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'variation_id')->withoutGlobalScope(ProductVariation::HIDE_DEFAULT);
    }

    /**
     * Whether this unit appears on any line yet (ProductUnit::USED_ON), which
     * locks its factor.
     */
    public function isUsed(): bool
    {
        foreach (self::USED_ON as $table) {
            if (DB::table($table)->where('unit_id', $this->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Finds a unit of $variation by uuid, within the current business,
     * including soft-deleted units (a sale line naming a since-deleted unit
     * still resolves). A unit of another variation, or another business,
     * isn't found.
     */
    public static function resolveFor(ProductVariation $variation, string $uuid): ?self
    {
        return static::withTrashed()->where('variation_id', $variation->id)->where('uuid', $uuid)->first();
    }

    /**
     * The precision a free-text unit name suggests (stage 11, steps 2 and 7):
     * whole-piece names stay whole; names for things sold by weight or volume
     * allow three decimals. Anything else defaults to whole units.
     */
    public static function guessPrecision(?string $name): int
    {
        $fractional = ['kg', 'g', 'liter', 'litre', 'l', 'ml', 'meter', 'metre', 'm'];

        return in_array(mb_strtolower(trim((string) $name)), $fractional, true) ? 3 : 0;
    }
}
