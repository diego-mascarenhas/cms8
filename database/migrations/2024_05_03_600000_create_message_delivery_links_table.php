<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up()
	{
		Schema::create('message_delivery_links', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('message_delivery_id');
			$table->string('link', 255);
			$table->unsignedInteger('click_count')->default(0);
			$table->timestamp('created_at');
			$table->timestamp('updated_at')->nullable();

			$table->foreign('message_delivery_id')->references('id')->on('message_deliveries')->onDelete('cascade');
		});
	}

	public function down()
	{
		Schema::dropIfExists('message_delivery_links');
	}
};
