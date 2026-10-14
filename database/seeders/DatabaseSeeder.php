<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Platform essentials only (decision 4.7): permissions and role
     * templates. Businesses, their shops and people start empty; they come
     * from signing up or the legacy import, and demo data from the live-like
     * dataset (the dataset:build command).
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);
    }
}
