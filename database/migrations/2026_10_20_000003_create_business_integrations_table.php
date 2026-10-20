<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A business's own credentials for outside services (stage 3): SMS, WhatsApp
 * Cloud, and a shop's M-Pesa account. Credentials are stored encrypted. A row
 * with no shop belongs to the whole business.
 *
 * Additive: a new table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_integrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 30)->comment('sms, whatsapp_cloud or mpesa');
            $table->text('credentials')->comment('Encrypted JSON');
            $table->string('status', 20)->default('active')->comment('active, disabled or failed');
            $table->timestamp('verified_at')->nullable();
            $table->string('last_error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'type']);
            $table->index(['shop_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_integrations');
    }
};
