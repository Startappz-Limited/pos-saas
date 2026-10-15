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
            $table->foreignId('business_id')->constrained();
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
            $table->boolean('alive')->nullable()->virtualAs('case when deleted_at is null then 1 end')->comment('1 until soft-deleted, then NULL: per-business unique keys skip deleted rows');
            $table->primary('id');
            $table->unique(['business_id', 'slug', 'alive']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
