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
		Schema::create('mkt_messages', function (Blueprint $table)
		{
			$table->id();
			$table->string('name');
			$table->enum('type', ['mailer', 'whatsapp']);
			//$table->unsignedInteger('list_id')->nullable();
			$table->foreignId('mkt_lists_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
			//$table->unsignedInteger('template_id')->nullable();
			$table->foreignId('mkt_templates_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
			$table->text('text');
			$table->tinyInteger('status')->default(2);
			$table->timestamps();

			// $table->foreign('template_id')->references('id')->on('mkt_templates')
			//     ->onUpdate('cascade')
			//     ->onDelete('cascade');

			// $table->foreign('list_id')->references('id')->on('mkt_lists')
			//     ->onUpdate('cascade')
			//     ->onDelete('cascade');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('mkt_messages');
	}
};
