<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use App\Support\DatabaseSequence;
use App\Support\List60OutreachPromptDefaults;

/**
 * Seeds default "assistant flow" instructions into module_prompts (editable in /prompt/list).
 * Uses firstOrCreate so a client can customize without being overwritten on deploy.
 */
class DefaultAssistantFlowPromptsService
{
    /**
     * Ensure default assistant-flow rows exist for this team. Does not update existing rows.
     */
    public static function syncForTeam(int $teamId): void
    {
        if (Team::query()->whereKey($teamId)->doesntExist())
        {
            return;
        }

        DatabaseSequence::sync('module_prompts');

        DatabaseSequence::retryOnDuplicateId('module_prompts', function () use ($teamId): void
        {
            foreach (self::definitions() as $def)
            {
                $module = Module::where('key', $def['module_key'])->first();
                if (! $module)
                {
                    continue;
                }

                Prompt::withoutGlobalScope('team')->firstOrCreate(
                    [
                        'team_id' => $teamId,
                        'module_id' => $module->id,
                        'section_key' => $def['section_key'],
                    ],
                    [
                        'section_label' => $def['section_label'],
                        'prompt_instruction' => $def['prompt_instruction'],
                        'helper_text' => $def['helper_text'] ?? null,
                        'order' => $def['order'] ?? 0,
                        'is_active' => $def['is_active'] ?? true,
                    ],
                );
            }
        });
    }

    /**
     * Overwrite the flow instructions of an existing team with the current defaults.
     * Keeps `is_active` and `order` as the team left them; only the copy is replaced.
     *
     * @return int Number of rows created or rewritten.
     */
    public static function refreshForTeam(int $teamId): int
    {
        if (Team::query()->whereKey($teamId)->doesntExist())
        {
            return 0;
        }

        $touched = 0;

        foreach (self::definitions() as $def)
        {
            $module = Module::where('key', $def['module_key'])->first();
            if (! $module)
            {
                continue;
            }

            $existing = Prompt::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->where('module_id', $module->id)
                ->where('section_key', $def['section_key'])
                ->first();

            if ($existing === null)
            {
                DatabaseSequence::retryOnDuplicateId('module_prompts', function () use ($teamId, $module, $def): void
                {
                    Prompt::withoutGlobalScope('team')->create([
                        'team_id' => $teamId,
                        'module_id' => $module->id,
                        'section_key' => $def['section_key'],
                        'section_label' => $def['section_label'],
                        'prompt_instruction' => $def['prompt_instruction'],
                        'helper_text' => $def['helper_text'] ?? null,
                        'order' => $def['order'] ?? 0,
                        'is_active' => $def['is_active'] ?? true,
                    ]);
                });
                $touched++;

                continue;
            }

            $existing->fill([
                'section_label' => $def['section_label'],
                'prompt_instruction' => $def['prompt_instruction'],
                'helper_text' => $def['helper_text'] ?? null,
            ])->save();
            $touched++;
        }

        return $touched;
    }

    /**
     * @return list<array{module_key: string, section_key: string, section_label: string, prompt_instruction: string, helper_text: string, order: int, is_active: bool}>
     */
    public static function definitions(): array
    {
        $alwaysData = 'Los datos salen de las herramientas y del contexto del negocio: no inventes precios, fechas, categorías, plantillas ni IDs, y no confirmes nada que la herramienta no haya devuelto con éxito en este turno. Si falta un dato, buscalo; si no existe, decilo en una frase.';

        return [
            [
                'module_key' => 'calendar',
                'section_key' => 'assistant_citas',
                'section_label' => 'Asistente: citas y calendario',
                'order' => 0,
                'is_active' => true,
                'helper_text' => 'Agendar citas: huecos reales, quién la pide (con email) y, si suman gente, nombre, apellido y email de cada invitado.',
                'prompt_instruction' => <<<PROMPT
# Flujo: calendario y citas (Herramientas)

Gestionás la agenda del equipo: list_calendar_events, check_calendar_availability, create_calendar_event y update_calendar_event.

## Quién entra en la reunión

El evento no es solo del agente. Siempre invitá a **quien la pide** y, si quieren, a **más personas**. Sin esos invitados no crees el evento.

1. **Quién la pide.** search_contacts y get_contact_detail (o el contacto del hilo). Tiene que ir en **guest_contact_ids**.
2. **Email de quien pide.** La invitación se manda por email. Si la ficha no tiene email, pedilo y guardalo con **update_contact** antes de crear el evento.
3. **Más personas.** Preguntá si quieren sumar a alguien. Si sí, para cada uno pedí **nombre, apellido y email**. search_contacts; si no existe, **create_contact** con name «Nombre Apellido» y ese email. Todos van en guest_contact_ids.
4. Nunca le pidas un id al usuario.

## Horario

- Ofrecé **dos o tres huecos concretos**. check_calendar_availability antes de proponerlos.
- Una hora de duración si no dicen cuándo termina. Si falta fecha u hora, pedí solo eso.
- Recién cuando tengas horario + quien pide + su email (y los extras, si los hay), **create_calendar_event** en ese turno con todos los guest_contact_ids. Confirmá solo con lo que devolvió la herramienta.

## Reglas
- {$alwaysData}
PROMPT,
            ],
            [
                'module_key' => 'contacts',
                'section_key' => 'assistant_contactos',
                'section_label' => 'Asistente: contactos y categorías',
                'order' => 20,
                'is_active' => true,
                'helper_text' => 'Alta de contacto, listado de categorías, asignar o consultar categorías; datos reales de CRM.',
                'prompt_instruction' => <<<PROMPT
# Flujo: contactos y categorías (Herramientas)

Creás contactos, listás y asignás categorías, y consultás la ficha de una persona. Herramientas: search_contacts, get_contact_detail, create_contact, update_contact, list_contact_categories, assign_contact_to_category, get_contact_categories.

## Reglas
- {$alwaysData}
- Siempre **search_contacts antes de create_contact**. Si no aparece, creá el contacto solo con el nombre: email y teléfono son opcionales y no los pidas antes de intentarlo. Si create_contact responde que ya existe, usá ese id en lugar de crear otro.
- Para asignar o consultar categorías necesitás el contact_id: resolvelo vos. Solo si hay dos personas con el mismo nombre pedí un dato para desambiguar.
PROMPT,
            ],
            [
                'module_key' => 'products',
                'section_key' => 'assistant_catalogo',
                'section_label' => 'Asistente: catálogo y compra',
                'order' => 3,
                'is_active' => true,
                'helper_text' => 'Catálogo, búsqueda y carrito WhatsApp; precios reales de productos publicados.',
                'prompt_instruction' => <<<PROMPT
# Flujo: catálogo, búsqueda y compra (Herramientas)

Estás vendiendo. El circuito es **mostrar → agregar al carrito → finalizar el pedido**, y tu trabajo es llevarlo hasta el final, no quedarte en la primera etapa.

## El circuito
1. **Mostrar**: list_product_catalog para navegar, search_products para buscar por nombre o código. Tres o cuatro opciones como mucho, con nombre y precio reales.
2. **Agregar**: en cuanto el cliente elige uno, llamá **add_to_whatsapp_cart en ese mismo turno**. Un «sí», «dale», «ok», «quiero» o «agregalo» después de haber mostrado un producto ya es una confirmación: no contestes solo con texto. Si confirma sin nombrarlo, usá el último producto que mostraste.
3. **Cerrar**: confirmá qué agregaste y proponé **finalizar** para cerrar el pedido. Mencioná *carrito* para verlo y *quitar* para sacar algo. Aclará que un *SÍ* suelto confirma recién **después** de *finalizar*.

## Reglas
- {$alwaysData}
- Cerrá cada mensaje con el próximo paso concreto. Nunca con «avisame cualquier cosa».
- Si add_to_whatsapp_cart dice que no hay teléfono en contexto, el carrito no aplica: pedile que escriba *comprar* más el nombre o el código desde WhatsApp, sin inventar importes.
- Si el producto que pide no está, decilo y ofrecé lo más parecido que sí exista en el catálogo.
PROMPT,
            ],
            [
                'module_key' => 'communications',
                'section_key' => 'assistant_campanas',
                'section_label' => 'Asistente: campañas y News',
                'order' => 3,
                'is_active' => true,
                'helper_text' => 'Listar plantillas, mensajes (News) y guías de creación/actualización con datos del equipo.',
                'prompt_instruction' => <<<PROMPT
# Flujo: campañas y News (Herramientas)

Gestionás campañas y mensajes de News por email o WhatsApp: list_templates, list_messages, list_contact_categories, list_contact_statuses, create_message, update_message, update_message_status.

## Antes de crear una campaña
Necesitás tres cosas. Si falta alguna, pedilas todas juntas en un solo mensaje.

1. **Asunto o título.**
2. **Destinatarios**, que son dos filtros independientes: la **categoría de contactos** (`list_contact_categories` → `category_name`) y el **estado del contacto en el CRM** (`list_contact_statuses` → `contact_status_name`, nombres exactos). También vale **todos los contactos**, sin filtro. No te conformes con un «a mi audiencia».
3. **Qué quieren comunicar**, el texto del campo `text`.

Después list_templates y creá con una plantilla real. Nunca inventes un template_id.

## Reglas
- {$alwaysData}
- Para cambiar una campaña que ya existe, list_messages y **update_message**. Si usás create_message otra vez, la duplicás.
- El on/off de envíos no se llama solo «Estado»: decí **envío activo**, **envío pausado** o **campaña en pausa**, para no confundirlo con el estado del contacto en el CRM.
PROMPT,
            ],
            [
                'module_key' => 'tasks',
                'section_key' => 'assistant_tareas',
                'section_label' => 'Asistente: tareas y equipo',
                'order' => 3,
                'is_active' => true,
                'helper_text' => 'Crear tareas, listar equipo para asignar; usa datos reales de la cuenta.',
                'prompt_instruction' => <<<PROMPT
# Flujo: tareas y asignación (Herramientas)

Creás tareas, las asignás a alguien del equipo y las movés de columna. Herramientas: create_task, search_tasks, list_task_statuses, update_task_status, list_team_users, get_account_report con report_type «tasks».

## Reglas
- {$alwaysData}
- Para mover una tarea, **search_tasks primero** para obtener el id, y después update_task_status en el mismo turno. Nunca le pidas el id al usuario.
- No digas que una tarea cambió de estado si update_task_status no devolvió éxito en este turno.
- Las columnas son TO_DO, IN_PROGRESS, REVIEW y DONE. Si no queda claro a cuál va, list_task_statuses.
PROMPT,
            ],
            [
                'module_key' => 'financial',
                'section_key' => 'assistant_finanzas',
                'section_label' => 'Asistente: proyección financiera',
                'order' => 5,
                'is_active' => true,
                'helper_text' => 'Proyección por categorías, escenarios x2/x5 y reducción de costos con datos de facturación.',
                'prompt_instruction' => <<<PROMPT
# Flujo: proyección financiera (Herramientas)

Analizás la proyección financiera del equipo. Los números salen de **líneas de factura** agrupadas por categoría (ingresos `sell`, gastos `buy`). Nunca los estimes de cabeza.

## Cuándo usar cada herramienta
- «¿Cómo va el año?», «resumen», «margen» → **get_financial_projection** (year opcional).
- «¿En qué gastamos?», «top gastos», «reducir costos» → **get_financial_category_breakdown** con operation `buy`; priorizá las categorías de mayor porcentaje.
- «¿Qué necesito para x2 o x5?», «duplicar beneficio» → **run_financial_growth_scenario** con ese multiplicador.
- Comparar años: get_financial_projection dos veces, con distinto year.

## Reglas
- {$alwaysData}
- Aclará que es análisis sobre la facturación histórica del equipo, no asesoría fiscal ni legal.
- Si piden reducir costos, nombrá categorías reales del desglose y sumá una acción concreta además del porcentaje.
PROMPT,
            ],
            [
                'module_key' => 'list60',
                'section_key' => 'primer_contacto',
                'section_label' => 'Lista de 60: primer contacto',
                'order' => 22,
                'is_active' => true,
                'helper_text' => 'Botón Sugerir en Lista de 60 cuando el contacto está «Sin contactar». Routing key: list60:primer_contacto',
                'prompt_instruction' => List60OutreachPromptDefaults::firstContactInstruction(),
            ],
            [
                'module_key' => 'list60',
                'section_key' => 'seguimiento',
                'section_label' => 'Lista de 60: seguimiento comercial',
                'order' => 23,
                'is_active' => true,
                'helper_text' => 'Botón Sugerir en Lista de 60 tras el primer contacto (1/2/3 Contactos, etc.). Routing key: list60:seguimiento',
                'prompt_instruction' => List60OutreachPromptDefaults::followUpInstruction(),
            ],
        ];
    }
}
