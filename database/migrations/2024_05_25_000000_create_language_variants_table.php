<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('language_variants', function (Blueprint $table)
        {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('base_language', 2);
            $table->string('country_code', 2)->nullable();
            $table->string('native_name')->nullable();
            $table->string('flag', 2)->nullable();

            $table->foreign('base_language')->references('code')->on('languages');
        });
    }

    public function down()
    {
        Schema::dropIfExists('language_variants');
    }
};