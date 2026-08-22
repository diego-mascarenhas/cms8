<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contact_intents'))
        {
            return;
        }

        Schema::create('contact_intents', function (Blueprint $table)
        {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
        });

        DB::table('contact_intents')->insert([
            ['id' => 1, 'key' => 'buy', 'name' => 'Buy'],
            ['id' => 2, 'key' => 'update', 'name' => 'Update'],
            ['id' => 3, 'key' => 'work', 'name' => 'Work'],
            ['id' => 4, 'key' => 'cancel', 'name' => 'Cancel'],
            ['id' => 5, 'key' => 'other', 'name' => 'Other'],
            ['id' => 6, 'key' => 'unclear', 'name' => 'Unclear'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_intents');
    }
};
