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
		Schema::create('history', function (Blueprint $table)
		{
			$table->id();
			$table->string('ref');
			$table->string('keyword')->nullable();
			$table->longText('answer');
			$table->string('refSerialize');
			$table->string('phone');
			$table->longText('options');
			$table->longText('date')->useCurrent();
			$table->timestamp('created_at')->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('history');
	}
};
