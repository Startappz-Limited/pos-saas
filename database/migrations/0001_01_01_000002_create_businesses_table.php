<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tenant: every shop, product, list and customer belongs to one business
 * (decisions 4.1, 4.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->json('settings')->nullable();
            $table->string('status')->default('active')->index();
            $table->char('currency', 3)->default('KES')->comment('Every amount in this business (4.9)');
            $table->string('timezone')->default('Africa/Nairobi');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
