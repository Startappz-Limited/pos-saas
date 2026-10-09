<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleSource extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'color',
        'is_active',
        'sort_order',
    ];

    /**
     * The sale sources every new business starts with, so its first sale does
     * not fail for want of a source (a sale requires one).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function defaults(): array
    {
        return [
            [
                'name' => 'Website',
                'description' => 'Direct purchase from website',
                'icon' => 'solar:globus-bold-duotone',
                'color' => '#3b82f6',
                'sort_order' => 1,
            ],
            [
                'name' => 'WhatsApp',
                'description' => 'Order via WhatsApp',
                'icon' => 'ri:whatsapp-fill',
                'color' => '#25d366',
                'sort_order' => 2,
            ],
            [
                'name' => 'Facebook',
                'description' => 'Order via Facebook',
                'icon' => 'ri:facebook-fill',
                'color' => '#1877f2',
                'sort_order' => 3,
            ],
            [
                'name' => 'Instagram',
                'description' => 'Order via Instagram',
                'icon' => 'ri:instagram-fill',
                'color' => '#e4405f',
                'sort_order' => 4,
            ],
            [
                'name' => 'Google',
                'description' => 'Found via Google search/ads',
                'icon' => 'ri:google-fill',
                'color' => '#ea4335',
                'sort_order' => 5,
            ],
            [
                'name' => 'Abandoned Cart',
                'description' => 'Recovered abandoned cart',
                'icon' => 'solar:cart-large-minimalistic-bold-duotone',
                'color' => '#f59e0b',
                'sort_order' => 6,
            ],
            [
                'name' => 'Referral',
                'description' => 'Customer referral',
                'icon' => 'solar:users-group-two-rounded-bold-duotone',
                'color' => '#8b5cf6',
                'sort_order' => 7,
            ],
            [
                'name' => 'Reseller Group',
                'description' => 'Order from reseller',
                'icon' => 'solar:shop-2-bold-duotone',
                'color' => '#10b981',
                'sort_order' => 8,
            ],
        ];
    }

    /**
     * Give a business the default sale sources it does not have yet.
     */
    public static function seedDefaultsFor(Business $business): void
    {
        foreach (self::defaults() as $source) {
            self::withoutGlobalScopes()->firstOrCreate(
                ['business_id' => $business->id, 'name' => $source['name']],
                $source,
            );
        }
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get all sales from this source
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'source_id');
    }

    /**
     * Scope for active sources only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
