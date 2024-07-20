<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('communication_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('type_id')->nullable();
            $table->string('name');
            $table->text('message')->nullable();
            $table->json('gjs_data')->nullable();
            $table->string('url')->nullable();

            $table->foreign('type_id')->references('id')->on('communication_types')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('communication_templates');
    }
};
