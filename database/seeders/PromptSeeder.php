<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Prompt;
use Illuminate\Database\Seeder;

class PromptSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projectsModule = Module::where('key', 'projects')->first();
        $tasksModule = Module::where('key', 'tasks')->first();

        if (! $projectsModule && ! $tasksModule)
        {
            $this->command->warn('No modules found (projects/tasks). Skipping prompt seeder.');

            return;
        }

        $prompts = [];

        if ($projectsModule)
        {
            $prompts[] = [
                'module_id' => $projectsModule->id,
                'section_key' => 'description',
                'section_label' => 'Descripción del proyecto',
                'prompt_instruction' => "# Descripción del proyecto\n\nAyuda al usuario a redactar una **descripción clara y completa** del proyecto.\n\n## Debe incluir:\n\n- Objetivo del proyecto\n- Alcance y entregables\n- Criterios de aceptación\n- Restricciones o dependencias\n\n---\n\n**Tu objetivo**: Mejorar claridad y completitud de la descripción.",
                'helper_text' => "**Estructura sugerida:**\n\n1. ¿Qué se va a hacer?\n2. ¿Para quién?\n3. ¿Cuáles son los entregables?\n4. ¿Qué criterios definen que está terminado?",
                'order' => 0,
                'is_active' => true,
            ];
        }

        if ($tasksModule)
        {
            $prompts[] = [
                'module_id' => $tasksModule->id,
                'section_key' => 'description',
                'section_label' => 'Descripción de la tarea',
                'prompt_instruction' => "# Descripción de la tarea\n\nAyuda al usuario a definir una **tarea concreta y accionable**.\n\n## La tarea debe ser:\n\n- Específica y medible\n- Con un resultado claro\n- Con plazo o prioridad si aplica\n\n---\n\n**Tu objetivo**: Hacer la tarea más clara y ejecutable.",
                'helper_text' => "**Indica:**\n\n1. Qué hay que hacer\n2. Criterio de completado\n3. Prioridad o plazo (opcional)",
                'order' => 0,
                'is_active' => true,
            ];
        }

        foreach ($prompts as $data)
        {
            Prompt::updateOrCreate(
                [
                    'module_id' => $data['module_id'],
                    'section_key' => $data['section_key'],
                ],
                $data,
            );
        }

        $this->command->info('Prompts creados/actualizados correctamente.');
    }
}
