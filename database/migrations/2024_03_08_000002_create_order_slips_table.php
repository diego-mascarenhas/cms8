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
		Schema::create('order_slips', function (Blueprint $table)
		{
			$table->increments('id');
			$table->foreignId('user_id')->constrained()->onDelete('cascade');
			$table->unsignedTinyInteger('command_id');
			$table->json('data');
			$table->tinyInteger('status')->default(1);
			$table->timestamps();

			$table->foreign('command_id')->references('id')->on('order_slips_commands')
				->onUpdate('cascade')
				->onDelete('cascade');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('order_slips');
	}
};