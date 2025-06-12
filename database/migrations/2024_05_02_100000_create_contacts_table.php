<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->unsignedTinyInteger('source_id')->nullable();
            $table->date('birthday')->nullable();
            $table->text('profile')->nullable();
            $table->enum('engagment', ['cold', 'temperate', 'hot'])->default('temperate');
            $table->unsignedSmallInteger('country')->default(724);
            $table->string('language', 2)->default('es');
            $table->foreignId('creator_id')->constrained('users');
            $table->foreignId('responsible_id')->nullable()->constrained('users');
            $table->json('data')->nullable();
            $table->unsignedSmallInteger('valoration_id')->nullable();
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('source_id')->references('id')->on('sources')->onDelete('restrict');
            $table->foreign('country')->references('id')->on('countries')->onDelete('restrict');
            $table->foreign('language')->references('code')->on('languages')->onDelete('restrict');
            $table->foreign('status_id')->references('id')->on('contact_statuses')->onDelete('restrict');
            $table->foreign('valoration_id')->references('id')->on('contact_valorations')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('contacts');
    }
};
