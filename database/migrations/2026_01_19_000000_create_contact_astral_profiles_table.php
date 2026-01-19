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
        Schema::create('contact_astral_profiles', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('contact_id')->unique()->constrained()->cascadeOnDelete();

            // Birth data (input)
            $table->date('birth_date');
            $table->time('birth_time')->nullable();
            $table->string('birth_city')->nullable();
            $table->decimal('birth_latitude', 10, 7)->nullable();
            $table->decimal('birth_longitude', 10, 7)->nullable();
            $table->string('birth_timezone')->nullable();

            // Calculated data (output)
            $table->string('zodiac_sign');
            $table->string('zodiac_symbol', 10);
            $table->string('zodiac_element');
            $table->string('north_node_sign');
            $table->string('ascendant_sign')->nullable();
            $table->json('human_design_data')->nullable();
            $table->text('interpretation')->nullable();
            $table->boolean('is_complete')->default(false);
            $table->timestamp('generated_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_astral_profiles');
    }
};
