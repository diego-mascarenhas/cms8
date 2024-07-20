<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedSmallInteger('type_id');
            $table->unsignedBigInteger('reference')->nullable()->index();
            $table->json('data')->nullable();
            $table->dateTime('sent')->nullable();
            $table->dateTime('received')->nullable();
            $table->string('link')->nullable();
            $table->tinyInteger('status')->default(1);

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('type_id')->references('id')->on('communication_types')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('communications');
    }
};
