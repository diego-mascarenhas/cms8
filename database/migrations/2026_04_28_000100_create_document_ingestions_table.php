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
        Schema::create('document_ingestions', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('source_id')->nullable();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->string('source_reference')->nullable();
            $table->string('file_name')->nullable();
            $table->text('file_url')->nullable();
            $table->string('mime_type', 191)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->enum('document_type', ['business_card', 'invoice', 'payment_proof', 'unknown'])->default('unknown');
            $table->enum('classification_status', ['pending', 'classified', 'needs_review', 'processed', 'failed'])->default('pending');
            $table->decimal('classification_confidence', 5, 2)->nullable();
            $table->longText('ocr_text')->nullable();
            $table->json('extracted_data')->nullable();
            $table->json('classification_meta')->nullable();
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->text('processing_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('source_id')->references('id')->on('sources')->onDelete('restrict');
            $table->index(['team_id', 'source_id']);
            $table->index(['classification_status', 'document_type'], 'doc_ingestions_status_type_idx');
            $table->index('source_reference');
            $table->index('file_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_ingestions');
    }
};
