<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name');
            $table->string('ip')->nullable();
            $table->string('server_url')->unique();
            $table->string('username');
            $table->boolean('success');
            $table->tinyInteger('status_id');
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('servers');
    }
}; 