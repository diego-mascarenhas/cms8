<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('type_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('contact_status_id')->nullable();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->text('text');
            $table->boolean('status_id')->default(0);
            $table->boolean('show_unsubscribe')->default(1);
            $table->boolean('enable_open_tracking')->default(1);
            $table->boolean('enable_click_tracking')->default(1);
            $table->integer('min_hours_between_emails')->default(48);
            $table->unsignedBigInteger('team_id');
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status_id']);
            $table->index(['category_id']);
            $table->index(['type_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('messages');
    }
};
