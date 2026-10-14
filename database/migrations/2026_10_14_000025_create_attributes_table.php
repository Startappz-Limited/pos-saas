<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->uuid('id');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->json('values')->nullable();
            $table->enum('type', ['dropdown', 'radio', 'checkbox', 'color', 'button'])->default('dropdown');
            $table->integer('display_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->primary('id');
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
