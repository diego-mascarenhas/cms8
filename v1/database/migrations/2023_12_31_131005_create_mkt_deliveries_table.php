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
        Schema::create('mkt_deliveries', function (Blueprint $table)
        {
            $table->id();
            //$table->unsignedBigInteger('id_message');
            $table->foreignId('mkt_messages_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            //$table->unsignedBigInteger('id_contact');
            $table->foreignId('mkt_contacts_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->tinyInteger('status')->default(2);

            // $table->foreign('id_message')->references('id')->on('mkt_messages')
            //     ->onUpdate('cascade')
            //     ->onDelete('cascade');

            // $table->foreign('id_contact')->references('id')->on('mkt_contacts')
            //     ->onUpdate('cascade')
            //     ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mkt_deliveries');
    }
};