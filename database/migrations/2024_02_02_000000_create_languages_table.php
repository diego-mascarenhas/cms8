<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->string('code', 2)->primary(); // ISO 639-1 codes
            $table->string('name');
        });
    }

    public function down()
    {
        Schema::dropIfExists('languages');
    }
};
