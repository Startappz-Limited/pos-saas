<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->string('widget_type');
            $table->string('size')->default('medium');
            $table->integer('position')->default(0);
            $table->integer('row')->default(0);
            $table->integer('column')->default(0);
            $table->json('config')->nullable();
            $table->json('filters')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
            $table->index(['user_id', 'is_visible']);
            $table->unique('uuid');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
    }
};
