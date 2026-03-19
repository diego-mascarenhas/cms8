<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->string('name');
            $table->string('code', 64)->nullable();
            $table->text('description');
            $table->text('short_description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->unsignedInteger('currency_id');
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->boolean('status')->default(true);
            $table->string('catalog_status', 20)->default('publish');
            $table->string('stock_status', 20)->default('instock');
            $table->boolean('manage_stock')->default(false);
            $table->unsignedInteger('stock_quantity')->nullable();
            $table->string('image', 2048)->nullable();
            $table->json('size_options')->nullable();
            $table->json('color_options')->nullable();
            $table->boolean('whatsapp_enabled')->default(true);
            $table->timestamps();

            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('cascade');

            $table->unique(['team_id', 'code']);
            $table->index(['team_id', 'status']);
            $table->index(['team_id', 'store_id']);
            $table->index(['category_id', 'status']);
            $table->index('whatsapp_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
