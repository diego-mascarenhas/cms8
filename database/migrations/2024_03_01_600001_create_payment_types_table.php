<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('discount', 10, 2)->default(0.00);
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_types');
    }
};
