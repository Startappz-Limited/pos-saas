<?php

namespace App\Models;

use App\Support\DefaultRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * The account a shop owner (admin) runs. It owns the owner's shops and staff,
 * plus the lists those shops share: suppliers, categories, attributes, pricing
 * rules, expense categories, delivery companies and sale sources.
 */
class Business extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'owner_id',
        'created_by',
        'updated_by',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $business) {
            if (empty($business->uuid)) {
                $business->uuid = (string) Str::uuid();
            }
            if (auth()->check() && empty($business->created_by)) {
                $business->created_by = auth()->id();
            }
        });

        static::updating(function (self $business) {
            if (auth()->check()) {
                $business->updated_by = auth()->id();
            }
        });
    }

    protected static function booted(): void
    {
        // A new business starts with the default sale sources (a sale needs
        // one) and its own copy of the default roles (manager, cashier, ...)
        static::created(function (self $business): void {
            SaleSource::seedDefaultsFor($business);
            DefaultRoles::seedFor($business);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
