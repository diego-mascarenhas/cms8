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
        Schema::create('contact_language_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->string('source_language_code');
            $table->string('target_language_code');
            $table->unsignedTinyInteger('proficiency_level')->default(1); // 1-5 scale
            $table->boolean('is_certified')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Use a shorter name for the unique constraint
            $table->unique(['contact_id', 'source_language_code', 'target_language_code'], 'clv_lang_pair_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_language_variants');
    }
};
