<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            // Spatie teams on business_id (stage 1a): NULL for the role
            // templates every business assigns, a business id for its own roles.
            $table->foreignId('business_id')->nullable()->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['business_id', 'name', 'guard_name']);
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
