<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_imports', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->json('filters');
            $table->string('status', 20)->default('discovering');
            $table->boolean('discovery_complete')->default(false);
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('queued')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('copied')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('credits')->default(0);
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'created_at'], 'prospect_imports_team_created_idx');
        });

        Schema::table('prospect_usage_logs', function (Blueprint $table)
        {
            $table->foreignId('prospect_import_id')->nullable()->constrained('prospect_imports')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prospect_usage_logs', function (Blueprint $table)
        {
            $table->dropConstrainedForeignId('prospect_import_id');
        });

        Schema::dropIfExists('prospect_imports');
    }
};
