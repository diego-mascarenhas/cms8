<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('hosts', function (Blueprint $table) {
            $table->id();
            $table->string('host');
            $table->string('name');
            $table->string('power_state');
            $table->string('connection_state');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('hosts');
    }
};
