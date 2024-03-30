<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('trade_data', function (Blueprint $table)
		{
			$table->id();
			$table->string('symbol');
			$table->unsignedInteger('time_frame')->nullable();
			$table->unsignedDecimal('open', 10, 8);
			$table->unsignedDecimal('close', 10, 8);
			$table->unsignedDecimal('low', 10, 8);
			$table->unsignedDecimal('high', 10, 8);
			$table->string('tr5', 11)->nullable();
			$table->string('tr15', 11)->nullable();
			$table->string('tr240', 11)->nullable();
			$table->unsignedFloat('volume')->nullable();
			$table->unsignedFloat('volatility')->nullable();
			$table->unsignedFloat('rsi')->nullable();
			$table->unsignedFloat('macd')->nullable();
			$table->unsignedFloat('adx')->nullable();
			$table->json('data')->nullable();
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('trade_data');
	}
};