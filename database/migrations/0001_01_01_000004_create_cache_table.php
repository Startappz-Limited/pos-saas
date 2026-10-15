<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key');
            $table->mediumText('value');
            $table->integer('expiration');
            $table->primary('key');
            $table->index('expiration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache');
    }
};
