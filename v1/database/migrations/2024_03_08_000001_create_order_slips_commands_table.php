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
		Schema::create('order_slips_commands', function (Blueprint $table)
		{
			$table->unsignedTinyInteger('id')->autoIncrement();
			$table->string('name');
			$table->string('triggers')->nullable();
			$table->unsignedTinyInteger('action_id');

			$table->foreign('action_id')->references('id')->on('order_slips_actions')
				->onUpdate('cascade')
				->onDelete('cascade');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('order_slips_commands');
	}
};