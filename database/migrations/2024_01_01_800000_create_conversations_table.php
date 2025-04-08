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
		Schema::create('conversations', function (Blueprint $table) {
			$table->id();
			$table->string('message_sid')->nullable()->unique();
			$table->string('channel')->default('whatsapp'); // whatsapp, sms, email, etc.
			$table->string('from');
			$table->string('to');
			$table->text('body');
			$table->string('status')->default('received');
			$table->string('direction')->default('inbound');
			$table->json('media')->nullable();
			$table->json('metadata')->nullable();
			$table->timestamps();
			
			// Indexing for faster queries
			$table->index(['channel', 'from', 'to']);
			$table->index(['direction', 'status']);
			$table->index('created_at');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('conversations');
	}
};
