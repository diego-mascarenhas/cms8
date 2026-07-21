<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_steps', function (Blueprint $table)
        {
            $table->id();
            $table->unsignedBigInteger('automation_id');
            $table->string('key');
            $table->string('label');
            $table->string('prompt_key')->nullable();
            $table->text('instruction')->nullable();
            $table->boolean('is_entry')->default(false);
            $table->integer('position_x')->default(0);
            $table->integer('position_y')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['automation_id', 'key']);
            $table->foreign('automation_id')->references('id')->on('automations')->onDelete('cascade');
            $table->index(['automation_id', 'is_entry']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_steps');
    }
};
