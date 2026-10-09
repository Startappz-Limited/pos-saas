<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each business keeps its own lists, so their natural keys only need to be
     * unique within a business. Left global, a second business could not have a
     * "Walk-in" sale source or a "drinks" category once the first one did.
     *
     * @var array<string, array<int, string>>
     */
    private array $keys = [
        'suppliers' => ['code'],
        'categories' => ['slug'],
        'attributes' => ['slug'],
        'expense_categories' => ['slug', 'code'],
        'sale_sources' => ['name'],
    ];

    public function up(): void
    {
        foreach ($this->keys as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->dropUnique([$column]);
                    $table->unique(['business_id', $column]);
                }
            });
        }
    }

    /**
     * Fails if two businesses already share a value, which is the point: the
     * global key cannot come back while that data exists.
     */
    public function down(): void
    {
        foreach ($this->keys as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->dropUnique(['business_id', $column]);
                    $table->unique([$column]);
                }
            });
        }
    }
};
