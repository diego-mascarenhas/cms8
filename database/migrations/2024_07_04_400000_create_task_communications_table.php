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
		Schema::create('task_communications', function (Blueprint $table) {
			$table->id();
			$table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
			$table->foreignId('user_id')->constrained('users')->onDelete('cascade');
			$table->json('recipients');
			$table->enum('method', ['email', 'whatsapp', 'internal'])->default('internal');
			$table->string('subject')->nullable();
			$table->text('message');
			$table->string('response_token', 64)->nullable()->unique();
			$table->text('response')->nullable();
			$table->timestamp('response_at')->nullable();
			$table->timestamp('sent_at')->nullable();
			$table->timestamps();

			$table->index('task_id');
			$table->index('user_id');
			$table->index('sent_at');
			$table->index('response_token');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('task_communications');
	}
};
