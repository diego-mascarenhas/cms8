<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->text('description');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('discount', 10, 2)->nullable();
            $table->decimal('tax_percentage', 5, 2)->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories');
            $table->timestamps();
        }); 
    }

    public function down()
    {
        Schema::dropIfExists('invoice_items');
    }
};
