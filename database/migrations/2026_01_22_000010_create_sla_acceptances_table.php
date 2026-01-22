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
        Schema::create('sla_acceptances', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('sla_id')->constrained('slas')->onDelete('cascade');
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->onDelete('set null');
            $table->string('token')->unique();
            $table->string('accepted_by_email');
            $table->string('accepted_by_name')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['sla_id', 'accepted_at']);
            $table->index(['subscription_id', 'accepted_at']);
            $table->index('token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sla_acceptances');
    }
};
