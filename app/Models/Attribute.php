<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\AttributeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attribute extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<AttributeFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * Missing until Phase 5: creating, editing and switching an attribute
     * failed (the screens were hidden from everyone, see A4).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'description',
        'values',
        'type',
        'display_order',
        'is_required',
        'is_visible',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'values' => 'array',
            'is_required' => 'boolean',
            'is_visible' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_attributes')
            ->withPivot('value')
            ->withTimestamps();
    }
}
