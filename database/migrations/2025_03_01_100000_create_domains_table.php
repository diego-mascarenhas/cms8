<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain');
            $table->string('server_url');
            $table->string('username');
            $table->string('plan')->nullable();
            $table->boolean('status_id')->default(true);
            $table->string('site_type')->nullable();
            $table->string('php_version')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('needs_update')->default(false);
            $table->boolean('is_working')->default(true);
            $table->json('data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
