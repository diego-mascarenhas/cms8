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
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('code', 10);
            $table->string('name');
            $table->string('base_language', 2);
            $table->string('country_code', 2)->nullable();

            $table->foreign('base_language')->references('code')->on('languages');
            $table->foreign('team_id')->references('id')->on('teams');
            $table->unique(['code', 'team_id'], 'language_variants_code_team_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('language_variants');
    }
};
