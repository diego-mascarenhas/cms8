<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('message_delivery_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_delivery_id');
            $table->text('link');
            $table->integer('click_count')->default(0);
            $table->timestamps();

            $table->index(['message_delivery_id']);
            $table->index(['click_count']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('message_delivery_links');
    }
};
