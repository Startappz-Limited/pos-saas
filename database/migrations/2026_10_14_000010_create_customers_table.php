<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained();
            $table->uuid('uuid');
            $table->unsignedBigInteger('shop_id');
            $table->string('code');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('tax_pin', 32)->nullable();
            $table->string('phone')->nullable();
            $table->string('phone_normalized', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('customer_type')->default('retail');
            $table->boolean('allow_credit')->default(false);
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('credit_balance', 15, 2)->default(0);
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->uuid('client_uuid')->nullable()->comment('Set by the till that created it offline');
            $table->boolean('needs_review')->default(false)->comment('Possible duplicate created offline');
            $table->timestamps();
            $table->softDeletes();
            $table->boolean('alive')->nullable()->virtualAs('case when deleted_at is null then 1 end')->comment('1 until soft-deleted, then NULL: per-business unique keys skip deleted rows');
            $table->index('code');
            $table->unique(['business_id', 'code', 'alive']);
            $table->index('phone_normalized');
            $table->index(['shop_id', 'status']);
            $table->index('uuid');
            $table->unique('uuid');
            $table->unique(['business_id', 'client_uuid']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
