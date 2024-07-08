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
        Schema::create('services_type', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->text('desctiption')->nullable();
			$table->json('data')->nullable();
            $table->unsignedSmallInteger('currency_id')->default(1);
            $table->decimal('price', 8, 2)->nullable();
            $table->decimal('discount', 5, 2)->nullable();
            $table->unsignedTinyInteger('frequency')->default(1);
			$table->tinyInteger('status')->default(1);
			$table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services_type');
    }
};
