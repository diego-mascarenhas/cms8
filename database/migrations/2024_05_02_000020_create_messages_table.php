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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->unsignedInteger('type_id');
            $table->foreignId('category_id')->nullable()->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedTinyInteger('contact_status_id')->nullable();
            $table->foreignId('template_id')->nullable()->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->text('text');
            $table->tinyInteger('status_id')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('type_id')->references('id')->on('message_type')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('contact_status_id')->references('id')->on('contact_statuses')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
