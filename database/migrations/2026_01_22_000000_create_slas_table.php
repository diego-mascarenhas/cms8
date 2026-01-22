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
        Schema::create('slas', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('subscription_product_id')->constrained('subscription_products')->onDelete('cascade');
            $table->string('title');
            $table->text('content');
            $table->string('version')->default('1.0');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['subscription_product_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slas');
    }
};
