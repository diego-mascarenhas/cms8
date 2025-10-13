<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('countries', function (Blueprint $table)
        {
            $table->unsignedSmallInteger('id')->primary(); // ISO 3166-1
            $table->string('name');
            $table->string('code', 2)->unique(); // alfa-2 ISO 3166-1
        });
    }

    public function down()
    {
        Schema::dropIfExists('countries');
    }
};
