<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overdue_notices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('credit_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->string('notice_number')->unique();
            $table->string('type'); // OverdueNoticeType enum
            $table->integer('notice_level')->default(1);

            $table->decimal('overdue_amount', 15, 2);
            $table->integer('days_overdue');

            $table->string('delivery_method'); // email, sms, both
            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->boolean('is_acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();

            $table->text('message')->nullable();

            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index('uuid');
            $table->index(['credit_account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overdue_notices');
    }
};
