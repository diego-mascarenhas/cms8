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
		Schema::create('templates', function (Blueprint $table)
		{
			//$table->increments('id');
			$table->id();
			$table->string('name');
			$table->longText('code');
			$table->tinyInteger('status')->default(2);
			$table->timestamps();
			$table->softDeletes();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('templates');
	}
};
