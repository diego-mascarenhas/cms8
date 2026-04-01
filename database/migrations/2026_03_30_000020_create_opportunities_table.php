<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('opportunity_stage_id')->constrained('opportunity_stages')->restrictOnDelete();
            $table->string('name');
            $table->date('opened_at');
            $table->decimal('estimated_amount', 15, 2)->nullable();
            $table->unsignedInteger('currency_id')->nullable();
            $table->foreign('currency_id')->references('id')->on('currencies')->nullOnDelete();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->text('offering_summary')->nullable();
            $table->nullableMorphs('offering');
            $table->date('expected_close_at')->nullable();
            $table->unsignedTinyInteger('probability')->nullable();
            $table->foreignId('won_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->string('closed_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
