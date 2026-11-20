<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product barcodes waiting for an owner to give them to a unit (stage 11, step 7). A product
 * with real variations carried one barcode for the whole product; it moves to no hidden default
 * variation, so it waits here until assigned or dropped. Until then it is sent nowhere.
 *
 * New table, nothing to carry across.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unassigned_barcodes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('barcode', 100);
            $table->timestamps();
            $table->unique(['business_id', 'barcode']);
            $table->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unassigned_barcodes');
    }
};
