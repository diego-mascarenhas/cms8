<?php

use App\Models\Message;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_categories', function (Blueprint $table)
        {
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->unique(['message_id', 'category_id']);
        });

        if (! Schema::hasColumn('messages', 'category_id'))
        {
            return;
        }

        Message::withoutGlobalScopes()
            ->whereNotNull('category_id')
            ->orderBy('id')
            ->chunkById(200, function ($messages): void
            {
                foreach ($messages as $message)
                {
                    $categoryId = (int) $message->category_id;
                    if ($categoryId > 0)
                    {
                        $message->contactCategories()->syncWithoutDetaching([$categoryId]);
                    }
                }
            });

        Message::withoutGlobalScopes()
            ->whereNotNull('category_id')
            ->update(['category_id' => null]);
    }

    public function down(): void
    {
        Schema::dropIfExists('message_categories');
    }
};
