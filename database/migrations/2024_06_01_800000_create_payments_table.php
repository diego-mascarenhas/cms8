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
		Schema::create('payments', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('team_id');
			$table->unsignedBigInteger('enterprise_id')->nullable();
			$table->enum('transaction_type', ['income', 'expense']);
			$table->date('date');
			$table->unsignedBigInteger('invoice_id')->nullable();
			$table->unsignedBigInteger('account_id');  // payment_accounts.id
			$table->unsignedTinyInteger('type_id');  // payment_types.id
			$table->decimal('amount', 15, 2);
			$table->text('remarks')->nullable();
			$table->tinyInteger('status')->default(1);
			$table->timestamps();

			// Foreign keys
			$table
				->foreign('team_id')
				->references('id')
				->on('teams')
				->onUpdate('cascade')
				->onDelete('cascade');

			$table
				->foreign('enterprise_id')
				->references('id')
				->on('enterprises')
				->onUpdate('cascade')
				->onDelete('set null');

			$table
				->foreign('invoice_id')
				->references('id')
				->on('invoices')
				->onUpdate('cascade')
				->onDelete('set null');

			$table
				->foreign('account_id')
				->references('id')
				->on('payment_accounts')
				->onUpdate('cascade')
				->onDelete('cascade');

			$table
				->foreign('type_id')
				->references('id')
				->on('payment_types')
				->onUpdate('cascade')
				->onDelete('cascade');

			// Indexes
			$table->index(['team_id', 'date']);
			$table->index(['team_id', 'transaction_type']);
			$table->index(['team_id', 'status']);
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('payments');
	}
};
