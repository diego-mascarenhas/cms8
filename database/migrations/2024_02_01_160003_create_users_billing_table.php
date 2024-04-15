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
		Schema::create('users_billing', function (Blueprint $table)
		{
			$table->id();
			$table->foreignId('user_id')->constrained()->onDelete('cascade');
			$table->string('name');
			$table->string('address');
			$table->string('city');
			$table->string('state');
			$table->string('zip');
			$table->timestamps();
			$table->softDeletes();

			$table->unsignedSmallInteger('sys_country_id')->nullable();
			$table->foreign('sys_country_id')
				->references('id')->on('regions_countries')
				->onDelete('set null');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('users_billing');
	}
};
