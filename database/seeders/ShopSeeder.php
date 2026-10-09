<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;

class ShopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Shop owners (admin) and managers run shops; super-admin is the platform operator
        $managers = User::whereHas('roles', function ($query) {
            $query->whereIn('name', [Role::ADMIN, 'manager']);
        })->get();

        if ($managers->isEmpty()) {
            // Create some manager users if none exist
            $managers = User::factory()
                ->count(3)
                ->create()
                ->each(function ($user) {
                    $user->assignRole('manager');
                });
        }

        // Create 5 active shops with different managers
        $shops = [
            [
                'name' => 'Main Branch',
                'code' => 'MAIN-001',
                'city' => 'Nairobi',
            ],
            [
                'name' => 'Downtown Store',
                'code' => 'DOWN-001',
                'city' => 'Mombasa',
            ],
            [
                'name' => 'Westlands Outlet',
                'code' => 'WEST-001',
                'city' => 'Nairobi',
            ],
            [
                'name' => 'Kisumu Branch',
                'code' => 'KIS-001',
                'city' => 'Kisumu',
            ],
            [
                'name' => 'Nakuru Store',
                'code' => 'NAK-001',
                'city' => 'Nakuru',
            ],
        ];

        foreach ($shops as $index => $shopData) {
            $shop = Shop::factory()->create([
                ...$shopData,
                'manager_id' => $managers->random()->id,
            ]);

            // Assign some random users to each shop
            $shop->users()->attach(
                User::inRandomOrder()->limit(rand(3, 7))->pluck('id')
            );
        }

        // Create 1 inactive shop
        Shop::factory()
            ->inactive()
            ->create([
                'name' => 'Eldoret Branch (Inactive)',
                'code' => 'ELD-001',
                'city' => 'Eldoret',
                'manager_id' => $managers->random()->id,
            ]);

        // Create 1 suspended shop
        Shop::factory()
            ->suspended()
            ->create([
                'name' => 'Thika Outlet (Suspended)',
                'code' => 'THK-001',
                'city' => 'Thika',
                'manager_id' => $managers->random()->id,
            ]);
    }
}
