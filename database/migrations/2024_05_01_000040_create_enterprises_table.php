<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up()
	{
		Schema::create('enterprises', function (Blueprint $table) {
			$table->id();
			$table->foreignId('team_id')->nullable()->constrained()->onDelete('cascade');
			$table->unsignedTinyInteger('type_id')->default(1);
			$table->string('name');
			$table->string('code')->nullable();
			$table->string('website')->nullable();
			$table->string('phone')->nullable();
			$table->string('email')->nullable();
			$table->string('whatsapp')->nullable();
			$table->string('referred_by')->nullable();
			$table->string('address')->nullable();
			$table->string('postal_code')->nullable();
			$table->string('locality')->nullable();
			$table->string('province')->nullable();
			$table->string('country')->nullable();
			$table->json('data')->nullable();  // Add this line
			$table->unsignedTinyInteger('payment_type_id')->nullable();
			$table->unsignedTinyInteger('invoice_type_id')->nullable();
			$table->unsignedTinyInteger('status_id')->default(1);
			$table->foreignId('creator_id')->nullable()->constrained('users')->onDelete('set null');
			$table->unsignedBigInteger('responsible_id')->nullable();
			$table->timestamps();
			$table->softDeletes();

			$table->index(['code']);
			$table->index('payment_type_id');  // Index only, not foreign key

			$table->foreign('type_id')->references('id')->on('enterprise_types')->onDelete('cascade');
			$table->foreign('invoice_type_id')->references('id')->on('invoice_types')->onDelete('cascade');
			$table->foreign('status_id')->references('id')->on('enterprise_statuses')->onDelete('restrict');
			$table->foreign('responsible_id')->references('id')->on('users')->onDelete('cascade');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down()
	{
		Schema::dropIfExists('enterprises');
	}
};
