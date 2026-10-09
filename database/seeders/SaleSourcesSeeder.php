<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\SaleSource;
use Illuminate\Database\Seeder;

class SaleSourcesSeeder extends Seeder
{
    /**
     * Run the database seeds: the default sale sources for every business.
     */
    public function run(): void
    {
        Business::query()->each(fn (Business $business) => SaleSource::seedDefaultsFor($business));
    }
}
