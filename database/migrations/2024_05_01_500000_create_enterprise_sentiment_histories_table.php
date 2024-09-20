<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEnterpriseSentimentHistoriesTable extends Migration
{
    public function up()
    {
        Schema::create('enterprise_sentiment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enterprise_id')->constrained('enterprises')->onDelete('cascade');
            $table->unsignedTinyInteger('sentiment_id');
            $table->foreign('sentiment_id')->references('id')->on('enterprise_sentiments')->onDelete('restrict');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('enterprise_sentiment_histories');
    }
}