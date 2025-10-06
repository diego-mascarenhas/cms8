<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up()
	{
		if (!Schema::hasTable('payment_types')) {
			Schema::create('payment_types', function (Blueprint $table) {
				$table->tinyIncrements('id');
				$table->string('name');
				$table->boolean('is_active')->default(true);
				$table->timestamps();
			});
		}

		// Note: payment_type_id in enterprises table is just an index, not a foreign key
	}

	public function down()
	{
		Schema::dropIfExists('payment_types');
	}
};
