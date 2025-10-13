<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notes', function (Blueprint $table)
        {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('module_id');
            $table->unsignedBigInteger('reference')->index();
            $table->string('name', 255);
            $table->text('description');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('notes');
    }
};
