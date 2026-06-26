<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table)
        {
            $table->id();
            $table->string('domain');
            $table->unsignedSmallInteger('server_id');
            $table->unsignedBigInteger('service_id')->nullable();
            $table->string('username');
            $table->string('plan')->nullable();
            $table->boolean('suspended')->default(0);
            $table->string('site_type')->nullable();
            $table->string('php_version')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('needs_update')->default(false);
            $table->boolean('is_working')->default(true);
            $table->json('data')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('server_id')
                ->references('id')
                ->on('servers')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
