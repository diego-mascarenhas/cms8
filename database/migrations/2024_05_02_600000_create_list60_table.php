<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('list60', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->unsignedTinyInteger('type_id')->default(1);
            $table->dateTime('date_next');
            $table->text('notes')->nullable();
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->timestamps();

            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->foreign('type_id')->references('id')->on('enterprise_types')->onDelete('cascade');
            $table->foreign('status_id')->references('id')->on('list60_statuses')->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::dropIfExists('list60');
    }
};