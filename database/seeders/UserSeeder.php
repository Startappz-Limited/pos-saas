<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Super Admin
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@fitness-center.test',
            'password' => Hash::make('password'),
            'phone' => '+1234567890',
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $superAdmin->assignRole('super-admin');

        // Create Manager
        $manager = User::create([
            'name' => 'Shop Manager',
            'email' => 'manager@fitness-center.test',
            'password' => Hash::make('password'),
            'phone' => '+1234567891',
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $manager->assignRole('manager');

        // Create Cashier
        $cashier = User::create([
            'name' => 'Cashier User',
            'email' => 'cashier@fitness-center.test',
            'password' => Hash::make('password'),
            'phone' => '+1234567892',
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $cashier->assignRole('cashier');

        // Create test users
        User::factory()->count(10)->create();

        // Create some inactive users
        User::factory()->count(3)->inactive()->create();

        // Create some suspended users
        User::factory()->count(2)->suspended()->create();

        $this->command->info('Users seeded successfully!');
    }
}
