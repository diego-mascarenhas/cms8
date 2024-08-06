<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('prompts', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name');
            $table->unsignedTinyInteger('type_id')->nullable();
            $table->text('content');
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('type_id')->references('id')->on('prompt_types')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('prompts');
    }
};
