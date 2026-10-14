<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('color', 7)->default('#3b82f6');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique('name');
        });

        // Every sale needs a source; sales recorded before sources existed
        // were given this one.
        DB::table('sale_sources')->insert([
            'name' => 'Walk-in',
            'description' => 'Direct walk-in customer (default for old records)',
            'icon' => 'solar:shop-bold-duotone',
            'color' => '#6b7280',
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_sources');
    }
};
