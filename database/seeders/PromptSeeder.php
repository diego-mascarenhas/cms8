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
        $prompts = $this->getPromptDefinitions();

        if (empty($prompts))
        {
            $this->command->warn('No modules found. Skipping prompt seeder.');

            return;
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

    /**
     * Get prompt definitions for each module
     */
    private function getPromptDefinitions(): array
    {
        $prompts = [];

        // Projects Module
        if ($module = Module::where('key', 'projects')->first())
        {
            $prompts[] = [
                'module_id' => $module->id,
                'section_key' => 'description',
                'section_label' => 'Descripción del proyecto',
                'prompt_instruction' => "# Descripción del proyecto\n\nAyuda al usuario a redactar una **descripción clara y completa** del proyecto.\n\n## Debe incluir:\n\n- Objetivo del proyecto\n- Alcance y entregables\n- Criterios de aceptación\n- Restricciones o dependencias\n\n---\n\n**Tu objetivo**: Mejorar claridad y completitud de la descripción.",
                'helper_text' => "**Estructura sugerida:**\n\n1. ¿Qué se va a hacer?\n2. ¿Para quién?\n3. ¿Cuáles son los entregables?\n4. ¿Qué criterios definen que está terminado?",
                'order' => 0,
                'is_active' => true,
            ];
        }

        // Tasks Module
        if ($module = Module::where('key', 'tasks')->first())
        {
            $prompts[] = [
                'module_id' => $module->id,
                'section_key' => 'description',
                'section_label' => 'Descripción de la tarea',
                'prompt_instruction' => "# Descripción de la tarea\n\nAyuda al usuario a definir una **tarea concreta y accionable**.\n\n## La tarea debe ser:\n\n- Específica y medible\n- Con un resultado claro\n- Con plazo o prioridad si aplica\n\n---\n\n**Tu objetivo**: Hacer la tarea más clara y ejecutable.",
                'helper_text' => "**Indica:**\n\n1. Qué hay que hacer\n2. Criterio de completado\n3. Prioridad o plazo (opcional)",
                'order' => 0,
                'is_active' => true,
            ];
        }

        // Contacts Module
        if ($module = Module::where('key', 'contacts')->first())
        {
            $prompts[] = [
                'module_id' => $module->id,
                'section_key' => 'notes',
                'section_label' => 'Notas del contacto',
                'prompt_instruction' => "# Notas profesionales de contacto\n\nAyuda a redactar **notas profesionales y estructuradas** sobre interacciones con contactos.\n\n## Debe incluir:\n\n- Fecha y contexto de la interacción\n- Puntos clave de la conversación\n- Acuerdos o compromisos\n- Próximos pasos\n\n---\n\n**Tu objetivo**: Crear un registro claro y útil de la interacción.",
                'helper_text' => "**Ejemplo:** 'Reunión con cliente sobre..., se acordó..., próximos pasos...'",
                'order' => 0,
                'is_active' => true,
            ];

            $prompts[] = [
                'module_id' => $module->id,
                'section_key' => 'email',
                'section_label' => 'Email al contacto',
                'prompt_instruction' => "# Email profesional\n\nAyuda a redactar un **email profesional y efectivo**.\n\n## Características:\n\n- Saludo apropiado\n- Mensaje claro y conciso\n- Llamado a la acción específico\n- Cierre profesional\n\n---\n\n**Tu objetivo**: Mejorar claridad y profesionalismo del email.",
                'helper_text' => '**Indica:** Contexto, objetivo del email, tono (formal/informal)',
                'order' => 1,
                'is_active' => true,
            ];
        }

        // Enterprises Module
        if ($module = Module::where('key', 'enterprises')->first())
        {
            $prompts[] = [
                'module_id' => $module->id,
                'section_key' => 'description',
                'section_label' => 'Descripción de la empresa',
                'prompt_instruction' => "# Descripción de empresa\n\nAyuda a crear una **descripción profesional de la empresa**.\n\n## Debe incluir:\n\n- Sector y actividad principal\n- Tamaño y ubicación\n- Servicios o productos\n- Información relevante para la relación comercial\n\n---\n\n**Tu objetivo**: Crear un perfil completo y útil de la empresa.",
                'helper_text' => '**Incluye:** Sector, tamaño, ubicación, servicios principales',
                'order' => 0,
                'is_active' => true,
            ];
        }

        // Invoices Module
        if ($module = Module::where('key', 'invoices')->first())
        {
            $prompts[] = [
                'module_id' => $module->id,
                'section_key' => 'description',
                'section_label' => 'Descripción de servicios',
                'prompt_instruction' => "# Descripción de servicios facturados\n\nAyuda a redactar **descripciones claras de servicios** para facturas.\n\n## Debe ser:\n\n- Claro y específico\n- Con desglose si es necesario\n- Fácil de entender para el cliente\n- Profesional\n\n---\n\n**Tu objetivo**: Crear descripciones claras para facturación.",
                'helper_text' => '**Indica:** Servicio prestado, periodo, cantidad, detalles relevantes',
                'order' => 0,
                'is_active' => true,
            ];
        }

        // Communications Module
        if ($module = Module::where('key', 'communications')->first())
        {
            $prompts[] = [
                'module_id' => $module->id,
                'section_key' => 'message',
                'section_label' => 'Mensaje de comunicación',
                'prompt_instruction' => "# Mensaje de marketing/comunicación\n\nAyuda a crear **mensajes de comunicación efectivos**.\n\n## Características:\n\n- Título atractivo\n- Mensaje claro y persuasivo\n- Llamado a la acción (CTA)\n- Tono apropiado para la audiencia\n\n---\n\n**Tu objetivo**: Maximizar engagement y claridad del mensaje.",
                'helper_text' => '**Indica:** Audiencia, objetivo, tono (formal/casual/urgente)',
                'order' => 0,
                'is_active' => true,
            ];
        }

        // Services Module
        if ($module = Module::where('key', 'services')->first())
        {
            $prompts[] = [
                'module_id' => $module->id,
                'section_key' => 'description',
                'section_label' => 'Descripción del servicio',
                'prompt_instruction' => "# Descripción de servicio\n\nAyuda a crear una **descripción atractiva y completa** del servicio.\n\n## Debe incluir:\n\n- Qué incluye el servicio\n- Beneficios para el cliente\n- Duración o modalidad\n- Diferenciadores\n\n---\n\n**Tu objetivo**: Hacer el servicio atractivo y claro para clientes.",
                'helper_text' => '**Incluye:** Qué es, qué incluye, beneficios, duración',
                'order' => 0,
                'is_active' => true,
            ];
        }

        // Notes Module
        if ($module = Module::where('key', 'notes')->first())
        {
            $prompts[] = [
                'module_id' => $module->id,
                'section_key' => 'content',
                'section_label' => 'Contenido de la nota',
                'prompt_instruction' => "# Estructurar nota\n\nAyuda a **organizar y estructurar** notas de forma clara.\n\n## Mejoras:\n\n- Estructura con títulos y secciones\n- Puntos clave destacados\n- Acción items identificados\n- Formato legible\n\n---\n\n**Tu objetivo**: Hacer la nota más útil y fácil de consultar.",
                'helper_text' => '**La IA:** Estructurará tu nota, destacará puntos clave, identificará tareas',
                'order' => 0,
                'is_active' => true,
            ];
        }

        // Templates Module
        if ($module = Module::where('key', 'templates')->first())
        {
            $prompts[] = [
                'module_id' => $module->id,
                'section_key' => 'content',
                'section_label' => 'Contenido de plantilla',
                'prompt_instruction' => "# Plantilla de email/documento\n\nAyuda a crear **plantillas profesionales y reutilizables**.\n\n## Debe incluir:\n\n- Estructura clara con variables\n- Tono apropiado\n- Llamados a la acción\n- Formato profesional\n\n---\n\n**Tu objetivo**: Crear plantillas profesionales y efectivas.",
                'helper_text' => '**Indica:** Tipo de plantilla, tono, variables necesarias ({{nombre}}, {{empresa}})',
                'order' => 0,
                'is_active' => true,
            ];
        }

        // Landing / Strategy (Strategic Growth Framework - 12 pasos)
        $strategyModule = Module::whereIn('key', ['projects', 'contacts', 'tasks'])->first();
        if ($strategyModule)
        {
            $prompts[] = [
                'module_id' => $strategyModule->id,
                'section_key' => 'landing',
                'section_label' => 'Estrategia (12 pasos)',
                'prompt_instruction' => $this->getLandingStrategyPromptInstruction(),
                'helper_text' => 'Responde en base al manual de 12 pasos (Strategic Growth Framework). Marca cada requisito con ✓ o ✗.',
                'order' => 0,
                'is_active' => true,
            ];
        }

        return $prompts;
    }

    /**
     * Prompt instruction for landing: analyze business problem against Strategic Growth Framework (12 steps).
     * Response must mark each requirement with ✓ (needed) or ✗ (not applicable / already covered).
     */
    private function getLandingStrategyPromptInstruction(): string
    {
        return <<<'PROMPT'
Eres un asesor de negocio de Humano.app. Tu tarea es analizar la problemática de negocio que describe el usuario y responder en base al **Strategic Growth Framework** (manual de 12 pasos).

## Formato de respuesta obligatorio

Responde **siempre** con una lista de requisitos del framework, marcando cada uno con:
- **✓** = necesario o recomendado para solucionar esta problemática
- **✗** = no aplica o ya está cubierto en lo que describe

Usa exactamente esta estructura (los 12 bloques del framework). No inventes ítems; solo marca ✓ o ✗ y, si quieres, una frase muy breve de por qué.

---

## Los 12 pasos del Strategic Growth Framework

1. **Tu dossier comercial** (Cliente, Destino, Oferta, Storytelling)
2. **Tu fachada digital** (Web, RRSS, SEO/SEM, Estrategia contenido)
3. **Entender tu juego** (Audiencia, Dinero, Contactos)
4. **Tu embudo en automático** (Doblar lo que funciona)
5. **Tu embudo de operaciones** (Talento, Herramientas, IA)
6. **Tu business playbook** (Manual de procesos, Wiki Notion)
7. **Scale framework** (Up/Down/Cross, Creación de audiencia, Embudo stories, Warm up leads)
8. **Simplificar tu negocio** (80/20, 5' business pitch)
9. **Quitar al fundador** (Auditar Calendar, Buyback your time)
10. **Crear tus managers** (Liderazgo, Operativa diaria)
11. **Generar tu cultura** (Visionboard empresa, Visionboard empleados, Retiros de equipo)
12. **Business exit** (Auditar valor empresa, Plan de salida)

---

## Ejemplo de problemáticas de negocio (para orientar tu análisis)

- Falta de **automatización de procesos** que impide crecer de forma ordenada.
- **Desorden en archivos y documentos**: todo en Excel, correos o carpetas sin criterio, difícil de encontrar y auditar.
- Dependencia de una sola persona que sabe cómo se hace cada cosa.
- No hay un único lugar donde esté la información de clientes, proyectos o facturación.

Usa estos ejemplos como referencia para interpretar problemáticas similares que describa el usuario.

---

**Tu objetivo**: Tras leer la problemática del usuario, devolver la lista de los 12 pasos con ✓ o ✗ y, opcionalmente, una línea de contexto por cada uno. Responde en el mismo idioma que use el usuario.
PROMPT;
    }
}
