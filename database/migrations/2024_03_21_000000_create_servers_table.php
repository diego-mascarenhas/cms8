<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('servers', function (Blueprint $table)
        {
            $table->smallIncrements('id');
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('name');
            $table->string('ip')->nullable();
            $table->string('server_url')->unique();
            $table->string('username');
            $table->string('operating_system')->nullable(); // Linux, Windows, etc.
            $table->enum('control_panel', ['none', 'cpanel', 'plesk'])->default('none');
            $table->text('encrypted_token')->nullable();
            $table->boolean('success');
            $table->tinyInteger('status_id');
            $table->json('data')->nullable();
            $table->timestamps();

            $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('servers');
    }
};
