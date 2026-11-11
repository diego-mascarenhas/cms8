<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table)
        {
            $table->unsignedBigInteger('phone')->nullable()->after('name');
            $table->boolean('subscribed')->default(true)->after('password');
            $table->string('cover_photo_path', 2048)->nullable()->after('profile_photo_path');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table)
        {
            $table->dropColumn('phone');
        });

        Schema::table('users', function (Blueprint $table)
        {
            $table->dropColumn('subscribed');
        });

        Schema::table('users', function (Blueprint $table)
        {
            $table->dropSoftDeletes();
        });

        Schema::table('users', function (Blueprint $table)
        {
            $table->dropColumn('cover_photo_path');
        });
    }
};
