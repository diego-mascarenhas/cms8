<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_transitions', function (Blueprint $table)
        {
            $table->id();
            $table->unsignedBigInteger('automation_id');
            $table->unsignedBigInteger('from_step_id');
            $table->unsignedBigInteger('to_step_id')->nullable();
            $table->string('reply_type');
            $table->string('match_value')->nullable();
            $table->string('label')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('drawflow_output')->nullable();
            $table->timestamps();

            $table->foreign('automation_id')->references('id')->on('automations')->onDelete('cascade');
            $table->foreign('from_step_id')->references('id')->on('automation_steps')->onDelete('cascade');
            $table->foreign('to_step_id')->references('id')->on('automation_steps')->onDelete('set null');
            $table->index(['from_step_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_transitions');
    }
};
