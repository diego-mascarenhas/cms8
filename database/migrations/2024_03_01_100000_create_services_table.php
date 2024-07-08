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
            $table->unsignedInteger('type_id');
            $table->unsignedBigInteger('client_id');
            $table->enum('operation', ['Buy', 'Sell'])->default('Sell');
            $table->text('desctiption')->nullable();
			$table->json('data')->nullable();
            $table->unsignedSmallInteger('currency_id')->default(1);
            $table->decimal('price', 8, 2)->nullable();
            $table->decimal('discount', 5, 2)->nullable();
            $table->unsignedTinyInteger('frequency')->default(1);
            $table->date('last_billed')->nullable()->default(null);
            $table->date('next_billing')->nullable()->default(null);
            $table->date('expires_at')->nullable()->default(null);
			$table->tinyInteger('status')->default(1);
			$table->timestamps();
            $table->softDeletes();

            $table->foreign('type_id')->references('id')->on('services_type')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            
            $table->foreign('client_id')->references('id')->on('clients')
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
