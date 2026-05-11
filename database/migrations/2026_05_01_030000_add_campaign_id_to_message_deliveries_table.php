<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_deliveries', function (Blueprint $table)
        {
            $table->foreignId('campaign_id')->nullable()->after('contact_id')->constrained('campaigns')->nullOnDelete();

            $table->dropUnique('unique_message_contact');
            $table->unique(['message_id', 'contact_id', 'campaign_id'], 'message_contact_campaign_unique');
        });
    }

    public function down(): void
    {
        Schema::table('message_deliveries', function (Blueprint $table)
        {
            $table->dropUnique('message_contact_campaign_unique');
            $table->dropForeign(['campaign_id']);
            $table->dropColumn('campaign_id');

            $table->unique(['message_id', 'contact_id'], 'unique_message_contact');
        });
    }
};
