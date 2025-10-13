<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('message_deliveries', function (Blueprint $table)
        {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedSmallInteger('smtp_id')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('removed_at')->nullable();
            $table->tinyInteger('status_id')->default(1);

            // Email provider tracking
            $table->string('email_provider')->nullable()->index(); // mailbaby, sendgrid, mailgun, smtp, etc.
            $table->string('provider_message_id')->nullable()->index(); // Provider-specific message ID
            $table->string('delivery_status')->nullable(); // delivered, bounced, failed, etc.
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->json('provider_data')->nullable(); // Provider-specific webhook data

            $table->timestamps();

            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('message_id')->references('id')->on('messages')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('message_deliveries');
    }
};
