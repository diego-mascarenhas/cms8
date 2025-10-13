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
        Schema::create('enterprise_organizations', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->unsignedTinyInteger('department_id');
            $table->string('name');
            $table->text('description');
            $table->unsignedBigInteger('responsible_id');
            $table->string('time_allocation');
            $table->string('availability')->nullable();
            $table->unsignedTinyInteger('order')->default(0);
            $table->timestamps();

            $table->foreign('department_id')->references('id')->on('enterprise_departments')->onDelete('cascade');
            $table->foreign('responsible_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('enterprise_organizations');
    }
};
