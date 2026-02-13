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
                'section_key' => 'general',
                'section_label' => 'Prompt general (enrutador)',
                'prompt_instruction' => $this->getGeneralRouterPromptInstruction(),
                'helper_text' => 'Escribe o describe tu necesidad; la IA te enviará al flujo más adecuado (problemas de negocio, descripción de proyecto, email, notas, etc.).',
                'order' => 0,
                'is_active' => true,
            ];

            $prompts[] = [
                'module_id' => $module->id,
                'section_key' => 'notes',
                'section_label' => 'Notas del contacto',
                'prompt_instruction' => "# Notas profesionales de contacto\n\nAyuda a redactar **notas profesionales y estructuradas** sobre interacciones con contactos.\n\n## Debe incluir:\n\n- Fecha y contexto de la interacción\n- Puntos clave de la conversación\n- Acuerdos o compromisos\n- Próximos pasos\n\n---\n\n**Tu objetivo**: Crear un registro claro y útil de la interacción.",
                'helper_text' => "**Ejemplo:** 'Reunión con cliente sobre..., se acordó..., próximos pasos...'",
                'order' => 1,
                'is_active' => true,
            ];

            $prompts[] = [
                'module_id' => $module->id,
                'section_key' => 'email',
                'section_label' => 'Email al contacto',
                'prompt_instruction' => "# Email profesional\n\nAyuda a redactar un **email profesional y efectivo**.\n\n## Características:\n\n- Saludo apropiado\n- Mensaje claro y conciso\n- Llamado a la acción específico\n- Cierre profesional\n\n---\n\n**Tu objetivo**: Mejorar claridad y profesionalismo del email.",
                'helper_text' => '**Indica:** Contexto, objetivo del email, tono (formal/informal)',
                'order' => 2,
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

            $prompts[] = [
                'module_id' => $module->id,
                'section_key' => 'image_analysis',
                'section_label' => 'Análisis de imagen',
                'prompt_instruction' => "# Análisis de imagen\n\nEl usuario puede **subir una imagen** además de texto.\n\n- Si recibe una imagen: descríbela con detalle (elementos, colores, texto visible, contexto probable) y sugiere mejoras o usos (redes sociales, presentación, documentación).\n- Si solo hay texto: pide una imagen o ayuda a redactar un brief para crear una.\n\nResponde en español, de forma clara y estructurada.",
                'helper_text' => '**Prueba:** Sube una imagen (captura, logo, foto) y opcionalmente escribe qué quieres que analice o mejore. Usa el botón "Subir imagen" en la prueba.',
                'order' => 1,
                'is_active' => true,
            ];

            $prompts[] = [
                'module_id' => $module->id,
                'section_key' => 'voice_summary',
                'section_label' => 'Resumen para escuchar en audio',
                'prompt_instruction' => "# Resumen para voz\n\nGenera **textos breves y claros** pensados para ser **leídos en voz alta** (TTS).\n\n- Máximo 2-3 párrafos cortos.\n- Frases directas, sin listas largas.\n- Tono natural y conversacional.\n- Si el usuario pide un resumen de algo largo, condensa lo esencial en formato \"para escuchar\".\n\nEl usuario puede marcar \"Recibir la respuesta en audio\" para obtener la versión en voz.",
                'helper_text' => '**Prueba:** Escribe un tema o pega un texto largo para resumir. Activa "Recibir la respuesta en audio" para oír la respuesta con TTS (ElevenLabs).',
                'order' => 2,
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

            $prompts[] = [
                'module_id' => $module->id,
                'section_key' => 'notes_from_audio',
                'section_label' => 'Notas desde audio (voz a texto)',
                'prompt_instruction' => "# Notas a partir de un audio\n\nEl usuario puede **subir un archivo de audio** (reunión, nota de voz, podcast).\n\n- La entrada de audio se transcribe automáticamente.\n- Con el texto transcrito: estructura la información en **notas claras** (títulos, puntos clave, acuerdos, tareas).\n- Si además hay texto escrito, combínalo con lo dicho en el audio.\n\nResponde en español, con formato de notas listas para guardar o compartir.",
                'helper_text' => '**Prueba:** Sube un audio (mp3, wav, m4a) con el botón "Subir audio". La IA transcribirá y convertirá el contenido en notas estructuradas.',
                'order' => 1,
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

        // Landing / Strategy (12 pasos)
        $strategyModule = Module::whereIn('key', ['projects', 'contacts', 'tasks'])->first();
        if ($strategyModule)
        {
            $prompts[] = [
                'module_id' => $strategyModule->id,
                'section_key' => 'landing',
                'section_label' => 'Estrategia (12 pasos)',
                'prompt_instruction' => $this->getLandingStrategyPromptInstruction(),
                'helper_text' => 'Responde en base al manual de 12 pasos. Marca cada requisito con ✓ o ✗.',
                'order' => 0,
                'is_active' => true,
            ];
        }

        return $prompts;
    }

    /**
     * Instruction for the general router: classify user intent and return only a routing key.
     */
    private function getGeneralRouterPromptInstruction(): string
    {
        return <<<'PROMPT'
Eres un enrutador. Tu ÚNICA tarea es elegir el flujo correcto según la intención del usuario.

Responde con **exactamente una línea**, sin explicación ni texto adicional, usando solo una de estas claves:

- **contacts:landing** → Problemas de negocio, estrategia, crecimiento, diagnóstico de empresa, procesos desordenados, dependencia de una persona, falta de automatización, plan de negocio.
- **projects:description** → Descripción o definición de un proyecto, alcance, entregables.
- **tasks:description** → Definir una tarea, tarea concreta, qué hay que hacer.
- **contacts:notes** → Notas de reunión, notas de contacto, resumen de conversación con cliente.
- **contacts:email** → Redactar un email, correo profesional, escribir a un contacto.
- **enterprises:description** → Descripción de empresa, perfil de empresa, datos comerciales de empresa.
- **invoices:description** → Descripción de servicios para factura, facturación.
- **communications:message** → Mensaje de marketing, comunicación, campaña, CTA.
- **communications:image_analysis** → Analizar una imagen, describir imagen, mejorar imagen.
- **communications:voice_summary** → Resumen para escuchar en audio, texto para voz.
- **services:description** → Descripción de un servicio, oferta de servicio.
- **notes:content** → Estructurar una nota, organizar notas, contenido de nota.
- **notes:notes_from_audio** → Convertir audio en notas, transcribir y estructurar.
- **templates:content** → Plantilla de email o documento, contenido de plantilla.

Regla: si el usuario habla de **problemas de negocio, estrategia, crecimiento, desorden operativo, automatización, diagnóstico** → responde solo: contacts:landing
Para cualquier otra intención, elige la clave que mejor coincida. Responde solo la clave, nada más.
PROMPT;
    }

    /**
     * Prompt instruction for landing: analyze business problem against the 12-step manual.
     * Response must be in Markdown (bold, italic, links), mark each requirement with ✓ or ✗,
     * and end with the CTA line for the app to show the "profundizar" form.
     */
    private function getLandingStrategyPromptInstruction(): string
    {
        return <<<'PROMPT'
Eres un asesor de negocio de Humano.app. Analiza la problemática de negocio que describe el usuario y responde en base al manual de 12 pasos.

## Formato de respuesta obligatorio

- Responde en **Markdown**: usa **negrita**, *cursiva* y [enlaces](https://humano.app) cuando ayuden a hacer la respuesta más clara y amena.
- Lista los **requisitos** (los 12 pasos) marcando cada uno con **exactamente un** símbolo:
  - **✓** (uno solo) = necesario o recomendado para esta problemática
  - **✗** (uno solo) = no aplica o ya está cubierto
- Usa exactamente los 12 bloques siguientes. No inventes ítems; solo un ✓ o un ✗ por ítem y, si quieres, una frase breve. No uses ✓✓ ni dos tildes.
- **Al final** de tu respuesta incluye exactamente esta línea (para que la app muestre el formulario de contacto):
  ¿Te gustaría profundizar en alguno de estos puntos?

---

## Los 12 pasos

1. **Tu dossier comercial** (Cliente, Destino, Oferta, Storytelling)
2. **Tu fachada digital** (Web, RRSS, SEO/SEM, Estrategia contenido)
3. **Entender tu juego** (Audiencia, Dinero, Contactos)
4. **Tu embudo en automático** (Doblar lo que funciona)
5. **Tu embudo de operaciones** (Talento, Herramientas, IA)
6. **Tu business playbook** (Manual de procesos, Wiki Notion)
7. **Scale** (Up/Down/Cross, Creación de audiencia, Embudo stories, Warm up leads)
8. **Simplificar tu negocio** (80/20, 5' business pitch)
9. **Quitar al fundador** (Auditar Calendar, Buyback your time)
10. **Crear tus managers** (Liderazgo, Operativa diaria)
11. **Generar tu cultura** (Visionboard empresa, Visionboard empleados, Retiros de equipo)
12. **Business exit** (Auditar valor empresa, Plan de salida)

---

## Ejemplo de problemáticas de negocio (para orientar tu análisis)

- Falta de **automatización de procesos** que impide crecer de forma ordenada.
- **Desorden en archivos y documentos**: todo en Excel, correos o carpetas sin criterio.
- Dependencia de una sola persona que sabe cómo se hace cada cosa.
- No hay un único lugar donde esté la información de clientes, proyectos o facturación.

---

**Objetivo**: Devolver la lista de los 12 pasos con un solo ✓ o un solo ✗ por ítem en Markdown (negrita, cursiva, enlaces). Termina con la línea: ¿Te gustaría profundizar en alguno de estos puntos? Responde en el mismo idioma que use el usuario. No uses nunca la expresión "Strategic Growth Framework"; si nombras el análisis, usa "Análisis de la Estrategia".
PROMPT;
    }
}
