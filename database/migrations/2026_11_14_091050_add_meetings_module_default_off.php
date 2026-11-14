<?php

use App\Models\Module;
use App\Models\Team;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $module = Module::query()->updateOrCreate(
            ['key' => 'meetings'],
            [
                'name' => 'Meetings',
                'icon' => 'microphone',
                'description' => 'Meeting transcripts and recordings',
                'is_core' => false,
                'group' => 'operations',
                'order' => 9,
                'status' => 1,
            ],
        );

        Team::query()->orderBy('id')->chunkById(100, function ($teams) use ($module): void
        {
            foreach ($teams as $team)
            {
                if (! $team->hasModule('meetings'))
                {
                    $team->enableModule($module->key);
                }
            }
        });
    }

    public function down(): void
    {
        $module = Module::query()->where('key', 'meetings')->first();

        if ($module === null)
        {
            return;
        }

        $module->teams()->detach();
        $module->delete();
    }
};
