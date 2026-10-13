<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table): void
        {
            $table->id();
            $table->string('name');
            $table->string('surname')->nullable();
            $table->string('title')->nullable();
            $table->string('role')->nullable();
            $table->string('organization_name')->nullable();
            $table->unsignedSmallInteger('country')->nullable();
            $table->timestamps();

            $table->foreign('country')->references('id')->on('countries')->nullOnDelete();
        });

        Schema::create('person_emails', function (Blueprint $table): void
        {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('email');
            $table->timestamps();

            $table->unique('email');
        });

        Schema::create('person_enrichments', function (Blueprint $table): void
        {
            $table->id();
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('source');
            $table->string('external_id');
            $table->json('payload')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
        });

        Schema::create('person_searches', function (Blueprint $table): void
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('filters');
            $table->unsignedInteger('page')->default(1);
            $table->unsignedSmallInteger('per_page')->default(25);
            $table->timestamps();
        });

        Schema::create('person_search_results', function (Blueprint $table): void
        {
            $table->id();
            $table->foreignId('person_search_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('source');
            $table->string('external_id');
            $table->timestamps();

            $table->unique(['person_search_id', 'source', 'external_id']);
            $table->index(['source', 'external_id']);
        });

        Schema::table('contacts', function (Blueprint $table): void
        {
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('person_search_id')->nullable()->constrained('person_searches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table): void
        {
            $table->dropConstrainedForeignId('person_search_id');
            $table->dropConstrainedForeignId('person_id');
        });

        Schema::dropIfExists('person_search_results');
        Schema::dropIfExists('person_searches');
        Schema::dropIfExists('person_enrichments');
        Schema::dropIfExists('person_emails');
        Schema::dropIfExists('people');
    }
};
