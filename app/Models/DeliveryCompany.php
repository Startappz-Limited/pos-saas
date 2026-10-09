<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DeliveryCompany extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Boot method to auto-generate UUID
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the route key for the model
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Get all sales assigned to this delivery company
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'delivery_company_id');
    }

    /**
     * Scope for active delivery companies only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
