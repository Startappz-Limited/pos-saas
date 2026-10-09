<?php

namespace Database\Seeders;

use App\Models\SaleSource;
use Illuminate\Database\Seeder;

class SaleSourcesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sources = [
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

        foreach ($sources as $source) {
            SaleSource::firstOrCreate(
                ['name' => $source['name']],
                $source
            );
        }
    }
}
