<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;

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
    }

    /**
     * @return list<array{module_key: string, section_key: string, section_label: string, prompt_instruction: string, helper_text: string, order: int, is_active: bool}>
     */
    public static function definitions(): array
    {
        $alwaysData = 'El contexto del negocio (configuración del equipo) y los datos de las herramientas son la fuente de verdad: no inventes precios, contactos, fechas, categorías, plantillas o IDs. Si falta un dato, usá la herramienta adecuada para obtenerlo; si aún no está disponible, decilo en una frase.';

        return [
            [
                'module_key' => 'calendar',
                'section_key' => 'assistant_citas',
                'section_label' => 'Asistente: citas y calendario',
                'order' => 0,
                'is_active' => true,
                'helper_text' => 'Flujo para agendar, listar o revisar eventos con datos reales del calendario del equipo.',
                'prompt_instruction' => <<<PROMPT
# Flujo: calendario y citas (Herramientas)

Eres el asistente de este equipo. Ayudá a **crear, listar, consultar o modificar** eventos y disponibilidad usando **siempre** los datos reales de la agenda del equipo (herramientas: list_calendar_events, check_calendar_availability, create_calendar_event, update_calendar_event según el caso).

## Reglas
- {$alwaysData}
- Para "hoy" o "mañana" usá la **fecha actual real** (no asumas otra).
- Dejá horarios y títulos alineados con lo que pida el usuario; confirmá con datos de las herramientas.
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

Ayudá a **crear** contactos, **listar categorías**, **asignar** un contacto a una categoría (o crear la categoría si hace falta) y **consultar** en qué categorías está un contacto. Usá list_contact_categories, create_contact, assign_contact_to_category, get_contact_categories, update_contact según el caso.

## Reglas
- {$alwaysData}
- Necesitás contact_id para asignar o consultar categorías: si el usuario nombra a alguien, obtené el id con datos del equipo o pedí un dato mínimo (teléfono) para desambiguar.
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

Ayudá a mostrar el **catálogo**, buscar productos (nombre o código) y, cuando el contexto lo permita, **agregar al carrito de WhatsApp** del cliente. Usá list_product_catalog, search_products, add_to_whatsapp_cart.

## Reglas
- {$alwaysData}
- **add_to_whatsapp_cart** aplica al hilo de WhatsApp: si el usuario está solo en el chat web, explicá que el carrito se confirma en el contexto móvil/WhatsApp cuando corresponda, sin inventar importes.
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

Ayudá con **campañas / mensajes de News (email o WhatsApp)**: listar plantillas, listar mensajes existentes, y guiar la creación o actualización de campañas con plantilla real (list_templates, list_messages, create_message, update_message, update_message_status). No inventes template_id: obtené ids con list_templates / list_messages.

## Reglas
- {$alwaysData}
- Si piden crear **News**, **newsletter**, **email masivo** o **mensaje** de campaña: antes de **create_message** asegurate de tener **asunto/título**, **audiencia** (categoría de contacto con list_contact_categories y/o **filtro de estado del contacto en el CRM** con contact_status_name, o sin categoría si es para todos) y **qué quieren comunicar** (texto corto para el campo `text`). Si falta algo, preguntá en un solo mensaje. Luego list_templates y creá con plantilla real. Tras crear, la app puede abrir el editor; indicá que pueden seguir ahí. En resúmenes en español, el on/off de envíos **no** lo llames solo «Estado»: usá **envío de la campaña** / **campaña pausada** / **envío activo** para no confundir con el estado del contacto en el CRM.
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

Ayudá a **crear tareas** y **asignar** a un miembro del equipo cuando haga falta. Usá create_task, list_team_users, get_account_report (tasks) según el caso.

## Reglas
- {$alwaysData}
PROMPT,
            ],
        ];
    }
}
