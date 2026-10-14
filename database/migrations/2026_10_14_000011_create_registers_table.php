<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A till (device) in a shop (decision 4.2). Cash sessions (cash_registers)
 * run on a register; each register numbers its invoices in its own series so
 * it can sell offline (4.9). The model and code arrive in stage 1b.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->comment('In invoice numbers: INV-{shop}-{register}-{year}-{n}; a reinstalled device gets a new one');
            $table->string('device_id')->nullable();
            $table->timestamp('paired_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedBigInteger('last_invoice_number')->default(0)->comment('Last number seen in this register\'s series');
            $table->timestamps();
            $table->unique(['shop_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registers');
    }
};
