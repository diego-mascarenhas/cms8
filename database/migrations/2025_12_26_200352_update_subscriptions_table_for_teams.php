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
		Schema::table('subscriptions', function (Blueprint $table)
		{
			// Add team_id column after user_id
			$table->unsignedBigInteger('team_id')->nullable()->after('user_id');

			// Add index for team_id
			$table->index(['team_id', 'stripe_status'], 'subscriptions_team_id_stripe_status_index');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::table('subscriptions', function (Blueprint $table)
		{
			$table->dropIndex('subscriptions_team_id_stripe_status_index');
			$table->dropColumn('team_id');
		});
	}
};
