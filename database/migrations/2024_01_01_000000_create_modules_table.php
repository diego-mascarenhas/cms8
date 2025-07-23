<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create('modules', function (Blueprint $table) {
			$table->tinyIncrements('id');
			$table->string('name');
			$table->string('key')->unique();
			$table->unsignedTinyInteger('level')->nullable();
			$table->string('icon')->nullable();
			$table->text('description')->nullable();
			$table->boolean('is_core')->default(false);
			$table->tinyInteger('status')->default(1);
			$table->timestamps();
		});

		Schema::create('module_team', function (Blueprint $table) {
			$table->id();
			$table->unsignedTinyInteger('module_id');
			$table->unsignedBigInteger('team_id');
			$table->json('settings')->nullable();
			$table->tinyInteger('status')->default(1);
			$table->timestamps();

			$table->unique(['module_id', 'team_id']);

			$table->foreign('module_id')->references('id')->on('modules')
				->onUpdate('cascade')
				->onDelete('cascade');

			$table->foreign('team_id')->references('id')->on('teams')
				->onUpdate('cascade')
				->onDelete('cascade');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('module_team');
		Schema::dropIfExists('modules');
	}
};
