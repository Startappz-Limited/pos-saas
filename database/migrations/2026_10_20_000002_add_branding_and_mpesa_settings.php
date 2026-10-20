<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-owned settings (stage 3): a business's branding and invoice prefix
 * replace `config('app.name')` and the global invoice prefix on receipts and
 * PDFs; a shop's M-Pesa Till or Paybill replaces the number that was
 * hard-coded in the e-commerce order message.
 *
 * Additive: nullable columns, so existing businesses and shops read as before
 * (no prefix set means the platform's `INV`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('timezone');
            $table->text('receipt_header')->nullable()->after('logo_path');
            $table->text('receipt_footer')->nullable()->after('receipt_header');
            $table->string('invoice_prefix', 10)->nullable()->after('receipt_footer');
        });

        Schema::table('shops', function (Blueprint $table) {
            // Unique across the platform: a shortcode belongs to one shop (4.3).
            $table->string('mpesa_shortcode', 20)->nullable()->unique();
            $table->string('mpesa_shortcode_type', 10)->nullable()->comment('till or paybill');
            $table->timestamp('mpesa_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropUnique(['mpesa_shortcode']);
            $table->dropColumn(['mpesa_shortcode', 'mpesa_shortcode_type', 'mpesa_verified_at']);
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'receipt_header', 'receipt_footer', 'invoice_prefix']);
        });
    }
};
