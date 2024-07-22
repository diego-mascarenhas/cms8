<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('network_devices', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('name');
            $table->string('device_type'); // Switch or Router
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('network_devices');
    }
};
