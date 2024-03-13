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
		Schema::create('bruler_data', function (Blueprint $table)
		{
			$table->increments('id');
			$table->string('type');
			$table->json('data');
			$table->tinyInteger('status')->default(1);
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('bruler_data');
	}
};