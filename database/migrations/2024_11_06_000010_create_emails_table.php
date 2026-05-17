<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raw SQL (manual / reference):
 *
 * MySQL:
 * CREATE TABLE `emails` (
 *   `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 *   `mailbox_id` BIGINT UNSIGNED NOT NULL,
 *   `team_id` BIGINT UNSIGNED NOT NULL,
 *   `message_id` VARCHAR(255) NOT NULL,
 *   `subject` VARCHAR(255) NULL,
 *   `body_text` LONGTEXT NULL,
 *   `body_html` LONGTEXT NULL,
 *   `from_address` VARCHAR(255) NOT NULL,
 *   `to_address` TEXT NULL,
 *   `message_date` TIMESTAMP NULL,
 *   `seen` TINYINT(1) NOT NULL DEFAULT 0,
 *   `flagged` TINYINT(1) NOT NULL DEFAULT 0,
 *   `folder` VARCHAR(32) NOT NULL DEFAULT 'inbox',
 *   `created_at` TIMESTAMP NULL,
 *   `updated_at` TIMESTAMP NULL,
 *   INDEX `emails_message_id_index` (`message_id`),
 *   INDEX `emails_team_id_folder_index` (`team_id`, `folder`),
 *   UNIQUE `emails_mailbox_id_message_id_unique` (`mailbox_id`, `message_id`),
 *   CONSTRAINT `emails_mailbox_id_foreign` FOREIGN KEY (`mailbox_id`) REFERENCES `mailboxes` (`id`) ON DELETE CASCADE,
 *   CONSTRAINT `emails_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 *
 * PostgreSQL — tabla NUEVA (solo si `emails` no existe aún):
 * CREATE TABLE emails ( ... );  -- ver bloque completo en documentación del proyecto
 * CREATE INDEX IF NOT EXISTS emails_message_id_index ON emails (message_id);
 * CREATE UNIQUE INDEX IF NOT EXISTS emails_mailbox_id_message_id_unique ON emails (mailbox_id, message_id);
 * CREATE INDEX IF NOT EXISTS emails_team_id_folder_index ON emails (team_id, folder);
 *
 * PostgreSQL — tabla YA EXISTE (p. ej. migrada antes sin `folder`); ejecutar SOLO esto:
 * ALTER TABLE emails ADD COLUMN IF NOT EXISTS folder VARCHAR(32) NOT NULL DEFAULT 'inbox';
 * CREATE INDEX IF NOT EXISTS emails_team_id_folder_index ON emails (team_id, folder);
 * (No volver a crear emails_mailbox_id_message_id_unique: ya existe.)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('emails', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('mailbox_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('message_id')->index();
            $table->string('subject')->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->string('from_address');
            $table->text('to_address')->nullable();
            $table->timestamp('message_date')->nullable();
            $table->boolean('seen')->default(false);
            $table->boolean('flagged')->default(false);
            $table->string('folder', 32)->default('inbox');
            $table->timestamps();

            $table->unique(['mailbox_id', 'message_id']);
            $table->index(['team_id', 'folder']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
