<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping_carts', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('session_key', 80);
            $table->string('channel', 32);
            $table->timestamps();

            $table->unique(['team_id', 'channel', 'session_key'], 'shop_cart_team_ch_sess_uq');
            $table->index(['team_id', 'updated_at'], 'shop_cart_team_upd_idx');
        });

        Schema::create('shopping_cart_items', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('shopping_cart_id')->constrained('shopping_carts')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id');
            $table->string('name');
            $table->string('option_label')->nullable();
            $table->decimal('price', 10, 2)->unsigned()->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('currency_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('category_name')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['shopping_cart_id', 'product_variant_id'], 'shop_cart_item_cart_var_uq');
            $table->index(['team_id', 'product_id'], 'shop_cart_item_team_prod_idx');
            $table->index(['team_id', 'product_variant_id'], 'shop_cart_item_team_var_idx');
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('currency_id')->references('id')->on('currencies')->nullOnDelete();
            $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_cart_items');
        Schema::dropIfExists('shopping_carts');
    }
};
