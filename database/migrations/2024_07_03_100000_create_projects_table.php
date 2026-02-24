<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('projects', function (Blueprint $table)
        {
            $table->id();
            $table->unsignedBigInteger('board_id')->nullable();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('enterprise_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('responsible_id');
            $table->string('name', 255);
            $table->string('real_name', 255)->nullable();
            $table->text('description')->nullable();
            $table->json('data')->nullable()->comment('Presupuesto: budget_given, ai_interpretation, dimension, estimated_times, resources');
            $table->date('date_material')->nullable();
            $table->date('date_start')->nullable();
            $table->date('date_end')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('discount', 10, 2)->nullable();
            $table->unsignedTinyInteger('status_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('board_id')->references('id')->on('task_boards')->onDelete('set null');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('enterprise_id')->references('id')->on('enterprises')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            $table->foreign('responsible_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('status_id')->references('id')->on('project_statuses')->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::dropIfExists('projects');
    }
};
