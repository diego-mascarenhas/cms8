<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_price_lists', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('enterprise_id')->constrained('enterprises')->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->decimal('markup_percent', 8, 2)->default(0);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'imported_at']);
        });

        Schema::create('supplier_price_lines', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('supplier_price_list_id')->constrained('supplier_price_lists')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('supplier_code', 64)->nullable();
            $table->string('name');
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('min_qty', 10, 2)->nullable();
            $table->string('unit', 32)->nullable();
            $table->timestamps();

            $table->index(['supplier_price_list_id', 'product_id']);
            $table->index(['team_id', 'supplier_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_price_lines');
        Schema::dropIfExists('supplier_price_lists');
    }
};
