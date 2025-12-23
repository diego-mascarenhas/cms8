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
            $table->integer('subscribers')->default(0);
            $table->integer('remaining')->default(0);
            $table->integer('failed')->default(0);
            $table->integer('sent')->default(0);
            $table->integer('rejected')->default(0);
            $table->integer('delivered')->default(0);
            $table->integer('opened')->default(0);
            $table->integer('unsubscribed')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('unique_opens')->default(0);
            $table->decimal('ratio', 5, 2)->default(0);
            $table->timestamps();

            $table->unique('message_id');
            $table->index(['message_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('message_delivery_stats');
    }
};
