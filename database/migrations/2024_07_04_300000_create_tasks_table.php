<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tasks', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->foreignId('board_id')->nullable()->constrained('task_boards')->onDelete('set null');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('responsible_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->date('start_date');
            $table->date('due_date');
            $table->integer('order')->default(0);
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            $table->foreign('responsible_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('status_id')->references('id')->on('task_statuses')->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tasks');
    }
};
