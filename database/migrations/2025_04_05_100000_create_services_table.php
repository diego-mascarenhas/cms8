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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('enterprise_id');
            $table->enum('operation', ['buy', 'sell'])->default('sell');
            $table->text('description')->nullable();
			$table->json('data')->nullable();
            $table->unsignedSmallInteger('currency_id')->nullable();
            $table->decimal('price', 8, 2)->nullable();
            $table->decimal('discount', 5, 2)->nullable();
            $table->unsignedTinyInteger('frequency')->default(1);
            $table->date('last_billed')->nullable()->default(null);
            $table->date('next_billing')->nullable()->default(null);
            $table->date('expires_at')->nullable()->default(null);
            $table->foreignId('responsible_id')->nullable()->constrained('users');
			$table->tinyInteger('status')->default(1);
			$table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('categories')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            
            $table->foreign('enterprise_id')->references('id')->on('enterprises')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
