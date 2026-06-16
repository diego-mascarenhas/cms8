<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_customer_mappings', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enterprise_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 50)->index();
            $table->string('external_customer_id');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['enterprise_id', 'platform'], 'fiscal_customer_mappings_enterprise_platform_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_customer_mappings');
    }
};
