<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('message_delivery_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->unsignedInteger('subscribers')->nullable();
            $table->unsignedInteger('remaining')->nullable();
            $table->unsignedInteger('failed')->nullable();
            $table->unsignedInteger('sent')->nullable();
            $table->unsignedInteger('rejected')->nullable();
            $table->unsignedInteger('delivered')->nullable();
            $table->unsignedInteger('opened')->nullable();
            $table->unsignedInteger('unsubscribed')->nullable();
            $table->unsignedInteger('clicks')->nullable();
            $table->unsignedInteger('unique_opens')->nullable();
            $table->float('ratio')->nullable();
            $table->timestamps();

            $table->foreign('message_id')
                ->references('id')->on('messages')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('message_delivery_stats');
    }
};
