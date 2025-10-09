<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up()
	{
		Schema::create('times', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('team_id');
			$table->unsignedBigInteger('user_id');
			$table->unsignedBigInteger('task_id')->nullable();
			$table->string('description')->nullable();
			$table->dateTime('start_time');
			$table->dateTime('end_time')->nullable();
			$table->integer('duration_seconds')->nullable();
			$table->boolean('is_billable')->default(true);
			$table->decimal('hourly_rate', 10, 2)->nullable();
			$table->timestamps();
			$table->softDeletes();

			$table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
			$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
			$table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');

			$table->index(['team_id', 'user_id']);
			$table->index(['start_time', 'end_time']);
		});
	}

	public function down()
	{
		Schema::dropIfExists('times');
	}
};
