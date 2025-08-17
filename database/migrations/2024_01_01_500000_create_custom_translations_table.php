<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create('custom_translations', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('team_id');
			$table->string('key');  // Translation key (e.g., 'welcome')
			$table->text('value');  // Changed from JSON to text
			$table->string('locale', 5);  // Language code (e.g., 'es', 'en')
			$table->string('group')->default('app');  // Translation group (e.g., 'app', 'validation')
			$table->timestamps();

			$table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
			$table->unique(['team_id', 'key', 'locale', 'group']);
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('custom_translations');
	}
};
