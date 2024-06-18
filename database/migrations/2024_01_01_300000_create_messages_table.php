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
		Schema::create('messages', function (Blueprint $table)
		{
			$table->id();
			$table->string('name');
			$table->unsignedInteger('type_id');
			//$table->unsignedInteger('category_id')->nullable();
			$table->foreignId('category_id')->nullable()->constrained()->onUpdate('cascade')->onDelete('cascade');
			//$table->unsignedInteger('template_id')->nullable();
			$table->foreignId('template_id')->nullable()->constrained()->onUpdate('cascade')->onDelete('cascade');
			$table->text('text');
			$table->tinyInteger('status')->default(2);
			$table->timestamps();
			$table->softDeletes();

			$table->foreign('type_id')->references('id')->on('messages_type')
			    ->onUpdate('cascade')
			    ->onDelete('cascade');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('messages');
	}
};
