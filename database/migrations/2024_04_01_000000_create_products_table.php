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
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade'); // teams uses bigint
            $table->string('name');
            $table->text('description');
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('currency_id'); // currencies table uses unsignedInteger
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade'); // categories uses bigint
            $table->boolean('status')->default(true);
            $table->boolean('whatsapp_enabled')->default(true);
            $table->timestamps();

            // Foreign key constraint for currency (manual because it uses unsignedInteger)
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('cascade');

            // Indexes for better performance
            $table->index(['team_id', 'status']);
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
