<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('return_items', function (Blueprint $table) {
            $table->boolean('return_to_supplier')->default(false)->after('is_restocked');
            $table->timestamp('return_to_supplier_at')->nullable()->after('return_to_supplier');
            $table->text('return_to_supplier_notes')->nullable()->after('return_to_supplier_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return_items', function (Blueprint $table) {
            $table->dropColumn([
                'return_to_supplier',
                'return_to_supplier_at',
                'return_to_supplier_notes',
            ]);
        });
    }
};
