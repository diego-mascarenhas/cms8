<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_options', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'name']);
        });

        Schema::create('product_option_values', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('product_option_id')->constrained('product_options')->cascadeOnDelete();
            $table->string('value', 80);
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['product_option_id', 'value'], 'product_option_values_option_value_uq');
        });

        Schema::create('product_variants', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku', 64)->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->string('stock_status', 20)->default('instock');
            $table->boolean('manage_stock')->default(false);
            $table->unsignedInteger('stock_quantity')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'is_default']);
            $table->unique(['team_id', 'sku']);
        });

        Schema::create('product_variant_option_values', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('product_option_value_id')->constrained('product_option_values')->cascadeOnDelete();

            $table->unique(['product_variant_id', 'product_option_value_id'], 'product_variant_option_value_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_option_values');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_option_values');
        Schema::dropIfExists('product_options');
    }
};
