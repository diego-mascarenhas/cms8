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
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->json('data')->nullable();
            $table->boolean('is_core')->default(false);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('module_team', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('module_id');
            $table->unsignedBigInteger('team_id');
            $table->json('settings')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();

            $table->unique(['module_id', 'team_id']);
            
            $table->foreign('module_id')->references('id')->on('modules')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            
            $table->foreign('team_id')->references('id')->on('teams')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });

        // Add module_id field to categories table
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('module_id')->nullable()->after('name');
            $table->foreign('module_id')->references('id')->on('modules')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['module_id']);
            $table->dropColumn('module_id');
        });

        Schema::dropIfExists('module_team');
        Schema::dropIfExists('modules');
    }
}; 