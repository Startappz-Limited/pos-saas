<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Units of measure (stage 11, step 2): a variation is held and sold in one or
 * more units — a base unit (factor 1, the stock's own precision) and any
 * number of pack units ("1 Strip = 10 Tablet"). `shop_unit_prices` is the only
 * place a shop overrides a unit's price.
 *
 * Both tables are new; nothing reads or writes them before this stage, so
 * there is nothing to carry across.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('business_id')->constrained();
            $table->foreignId('variation_id')->constrained('product_variations')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('factor', 15, 3)->default(1)->comment('Base units in one of this unit; 1 for the base unit');
            $table->unsignedTinyInteger('precision')->default(0)->comment('Decimals this unit\'s quantities allow (0 to 3)');
            $table->string('barcode')->nullable();
            $table->decimal('selling_price', 10, 2)->nullable()->comment('Per this unit; NULL: base price x factor');
            $table->decimal('wholesale_price', 10, 2)->nullable();
            $table->boolean('is_base')->default(false);
            $table->boolean('can_sell')->default(true);
            $table->boolean('can_buy')->default(true);
            $table->boolean('is_default_purchase')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Exactly one base unit per variation (held by actions and a model guard too).
            $table->boolean('base_key')->nullable()->virtualAs('case when is_base = 1 then 1 end')
                ->comment('1 on the base unit, NULL otherwise: one base per variation');
            // At most one default-for-purchases unit per variation.
            $table->boolean('default_purchase_key')->nullable()->virtualAs('case when is_default_purchase = 1 then 1 end')
                ->comment('1 on the default-purchase unit, NULL otherwise: at most one per variation');
            // Soft-deleted units don't block a name or barcode being reused.
            $table->boolean('alive')->nullable()->virtualAs('case when deleted_at is null then 1 end')
                ->comment('1 until soft-deleted, then NULL: unique keys skip deleted rows');

            $table->unique('uuid');
            $table->unique(['variation_id', 'base_key']);
            $table->unique(['variation_id', 'default_purchase_key']);
            $table->unique(['variation_id', 'name', 'alive']);
            $table->unique(['business_id', 'barcode', 'alive']);
            $table->index(['variation_id', 'sort_order']);
        });

        Schema::create('shop_unit_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('product_units')->cascadeOnDelete();
            $table->decimal('selling_price', 10, 2)->nullable();
            $table->decimal('wholesale_price', 10, 2)->nullable();
            $table->timestamps();
            $table->unique(['shop_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_unit_prices');
        Schema::dropIfExists('product_units');
    }
};
