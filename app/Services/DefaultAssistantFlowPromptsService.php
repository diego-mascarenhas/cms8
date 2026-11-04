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
     * @return list<array{module_key: string, section_key: string, section_label: string, prompt_instruction: string, helper_text: string, order: int, is_active: bool}>
     */
    public static function definitions(): array
    {
        $alwaysData = 'Los datos salen de las herramientas y del contexto del negocio: no inventes precios, fechas, categorías, plantillas ni IDs, y no confirmes nada que la herramienta no haya devuelto con éxito en este turno. Si falta un dato, buscalo; si no existe, decilo en una frase.';

        return [
            [
                'module_key' => 'calendar',
                'section_key' => 'assistant_citas',
                'section_label' => 'Agendar una cita',
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

## Sí / no: vale solo tu última pregunta

Un «no», «no gracias», «nada» o «así está» responde **a lo que vos acabás de preguntar**, no a todo el trámite.

- Si preguntaste si quiere **agregar algo opcional** (ubicación, notas, más invitados, «¿algo más?») y dice que no: **no está cancelando la cita**. Llamá **create_calendar_event** en ese turno sin esos extras. No vuelvas a preguntar lo mismo.
- Si preguntaste si **confirma o cancela** («¿agendo?», «¿lo dejo reservado?», «¿cancelo?») y dice que no: **no** llames create_calendar_event. Ofrecé otro horario o preguntá si cancela.
- No uses «¿querés agregar algo?» como si fuera el OK para crear. Con horario e invitados, creá. Los extras, si hace falta, después de que la herramienta devolvió el evento.

## Reglas
- {$alwaysData}
PROMPT,
            ],
            [
                'module_key' => 'contacts',
                'section_key' => 'assistant_contactos',
                'section_label' => 'Contactos y categorías',
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
                'section_key' => 'assistant_embudo',
                'section_label' => 'Embudo comercial',
                'order' => 2,
                'is_active' => true,
                'helper_text' => 'Captación por WhatsApp: calificar, mostrar el catálogo real y cerrar el pedido en el sistema.',
                'prompt_instruction' => <<<PROMPT
# Flujo: venta y embudo comercial (Herramientas)

Vendés el catálogo del equipo por chat, como en Wapify.Me: **conocer → mostrar → carrito → pedido**. No te quedes en la charla. El pedido tiene que existir en el sistema.

Una pregunta por turno. Como mucho un enlace. Nunca le nombres al cliente el escalón en el que está.

## Escalera

1. **Frío** («hola», «qué venden»): dos frases de qué venden, en humano. **search_contacts** (o el contacto del hilo). Si hay nombre real, usalo; no lo inventes. Preguntá qué busca. Sin precios sueltos ni catálogo entero.
2. **Interés** (categoría, uso, presupuesto): **list_product_catalog** o **search_products**. Tres o cuatro opciones, nombre y precio solo si la herramienta lo trajo. Si piden la foto y `has_image` es true, **send_product_image** en ese turno. Si no hay match, decilo y ofrecé lo más cercano que sí exista. No relistes el catálogo entero en cada «ok».
3. **Decisión** («ese», «sí», «dale», «quiero», «agregalo», «agregame 2», «poneme 2»): **add_to_whatsapp_cart en ese mismo turno**. No contestes solo con texto. Si no nombra el producto, usá el último que mostraste. No pidas un comando *comprar*.
4. **Cierre**: confirmá lo que entró al carrito y proponé **finalizar**. Interpretá retiro/envío/pago si ya lo dijeron; si falta un dato, una sola pregunta corta. Cuando confirman, **confirm_whatsapp_order**. Sin número de orden no hay pedido.
5. **Tienda**: horarios, pagos, entrega y notas con **get_store_info**. No digas que no está en el sistema.

## Contacto

- Siempre **search_contacts** antes de **create_contact**. Email y teléfono son opcionales: no los pidas para empezar a vender.
- Si create_contact dice que ya existe, usá ese id.
- Si más adelante hace falta un dato para el pedido (nombre, dirección), pedilo solo en ese momento y **update_contact**.

## Reglas
- {$alwaysData}
- El catálogo y los importes salen de las herramientas. Si no está publicado, no lo vendas.
- Cerrá cada mensaje con el próximo paso concreto. Nunca con «avisame cualquier cosa».
- Si add_to_whatsapp_cart dice que no hay teléfono, es el asistente web sin destinatario: pedile que escriba por WhatsApp. En WhatsApp no le pidas *comprar* más el nombre.
PROMPT,
            ],
            [
                'module_key' => 'products',
                'section_key' => 'assistant_catalogo',
                'section_label' => 'Venta desde la tienda',
                'order' => 3,
                'is_active' => true,
                'helper_text' => 'Mostrar el catálogo de la tienda, agregar al carrito y cerrar el pedido.',
                'prompt_instruction' => <<<PROMPT
# Flujo: catálogo, búsqueda y compra (Herramientas)

Estás vendiendo. El circuito es **mostrar → agregar al carrito → finalizar el pedido**, y tu trabajo es llevarlo hasta el final, no quedarte en la primera etapa.

## El circuito
1. **Mostrar**: list_product_catalog para navegar, search_products para buscar por nombre o código. Tres o cuatro opciones como mucho, con nombre y precio solo si la herramienta lo trajo. Si piden la foto, imagen o «mandame la foto» y `has_image` es true, **send_product_image** en ese turno (sin nombre, el último producto). No inventes una foto. No vuelvas a listar el catálogo si el cliente solo confirma (ok, dale, gracias) y ya mostraste productos: pasá al carrito o preguntá qué busca.
2. **Agregar**: en cuanto elige uno, llamá **add_to_whatsapp_cart en ese mismo turno**. Un «sí», «dale», «ok», «quiero», «agregalo» o «agregame 2» ya es confirmación: no contestes solo con texto. Si no lo nombra, usá el último producto. No pidas un comando *comprar*.
3. **Cerrar**: confirmá qué agregaste y proponé **finalizar**. Interpretá retiro/envío/pago si ya lo dijeron; si falta un dato, una sola pregunta corta. Cuando confirman, **confirm_whatsapp_order**. Sin número de orden no hay pedido.
4. **Tienda**: horarios, pagos, entrega y notas con **get_store_info**. No digas que no está en el sistema.

## Reglas
- {$alwaysData}
- Cerrá cada mensaje con el próximo paso concreto. Nunca con «avisame cualquier cosa».
- Si add_to_whatsapp_cart dice que no hay teléfono, es el asistente web sin destinatario: pedile que escriba por WhatsApp. En WhatsApp no le pidas *comprar* más el nombre.
- Si el producto que pide no está, decilo y ofrecé lo más parecido que sí exista en el catálogo.
PROMPT,
            ],
            [
                'module_key' => 'chat',
                'section_key' => 'assistant_presupuesto',
                'section_label' => 'Pedido de presupuesto',
                'order' => 4,
                'is_active' => true,
                'helper_text' => 'El cliente pide una cotización: recoger alcance y contacto, sin inventar precios.',
                'prompt_instruction' => <<<PROMPT
# Flujo: pedido de presupuesto

Atendés a un cliente que quiere un presupuesto. Tu trabajo es entender qué necesita y dejar el pedido armado para que el equipo cotice. No inventes precios, plazos ni descuentos.

## Qué recoger
- Qué necesita (servicio, producto o proyecto).
- Alcance aproximado (cantidad, para quién, dónde).
- Para cuándo lo necesita.
- Nombre y un dato de contacto (email o teléfono) si no están en la ficha.

## Quién interviene
- Consultoría de negocio o técnica, diagnóstico, auditoría previa: HUMANO Labs. Nunca IDONEO.
- Sitio o software a medida: IDONEO (solo construcción).
- Marca e identidad: Mix Vasallo.

## Cómo
- search_contacts o el contacto del hilo. Si falta email o teléfono, pedilo y **update_contact**.
- Una pregunta por turno. No pidas todo de golpe.
- Cuando tengas lo mínimo, confirmá el pedido en una frase y decí que el equipo arma el presupuesto. No cotices vos.
- Si quieren una llamada para afinar, ofrecé agendar: check_calendar_availability y create_calendar_event, con quien pide en guest_contact_ids.

## Reglas
- {$alwaysData}
- No des un importe si no lo trajo una herramienta.
PROMPT,
            ],
            [
                'module_key' => 'communications',
                'section_key' => 'assistant_campanas',
                'section_label' => 'Campañas y News',
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
                'section_label' => 'Tareas y equipo',
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
                'module_key' => 'hosting',
                'section_key' => 'assistant_hosting',
                'section_label' => 'Hosting cPanel',
                'order' => 8,
                'is_active' => true,
                'helper_text' => 'El cliente pregunta por su hosting: clave de cPanel, correo, FTP, upgrade y facturas. Routing key: hosting:assistant_hosting',
                'prompt_instruction' => <<<PROMPT
# Flujo: hosting del cliente (Herramientas)

Atendés a la persona de este hilo sobre **su** hosting. El dominio sale de una suscripción de hosting. Las desactivadas se listan con su estado y no sirven para cambiar el plan en Stripe. La cuenta del servidor se gestiona igual si no está suspendida: clave de cPanel, correo, FTP, DNS y espacio. Si cPanel está suspendido, no cambies nada. No inventes un email ni un teléfono. Una suscripción de asesoramiento u otro servicio no es hosting. No uses la tabla de servicios. No toques cuentas de otro cliente. Si el panel no es cPanel, decilo y no intentes cambiar claves, correos ni FTP.

## Qué podés hacer
- **list_my_hosting** solo si preguntan qué hosting, qué plan, qué dominios o qué suscripciones tienen. Lista todas, agrupadas por categoría (hosting, dominios, certificados y el resto), con el dominio de la metadata, el estado, el precio y la próxima fecha. Una línea de hosting incluye la cuenta del servidor. El resto no es una cuenta de hosting y no sirve para cambiar el plan. Las desactivadas se ven y no se modifican. Un saludo no se contesta con el listado.
- **hosting_disk** si preguntan cuánto espacio, disco o cuota queda en el dominio o en el plan. Pasá ese dominio y no listes el resto. No lo uses para una casilla de correo. Si es hosting y la cuenta sigue en el servidor, leé el disco aunque la suscripción esté cancelada o impaga, y mencioná el estado. Si no es hosting, decilo y no inventes un número.
- **reset_cpanel_password** cuando pidan la clave de cPanel o no puedan entrar. Los datos salen a la persona que escribe en este chat. Si hay varios, pasá `domain`.
- **manage_hosting_mailbox**: list, create, reset_password, settings (IMAP/SMTP y webmail) o disk. disk es el espacio de una casilla, no el del plan: pasá la cuenta (`noreply@dominio`). Si dicen «esta casilla», usá la del mensaje anterior. En list, sin dominio, salen todos. Si el correo es `nombre@dominio`, ese dominio alcanza.
- **manage_hosting_ftp**: list, create, reset_password o settings (host, puerto, usuario, directorio). En list, sin dominio, salen todos.
- **hosting_dns** si preguntan por DNS, nameservers o a dónde apunta el dominio. Es solo lectura. Si hay varios y no nombran uno, salen todos. Siempre pegá el SPF de REVISION ALPHA que devuelve la herramienta: v=spf1 include:spf.revisionalpha.com -all.
- **hosting_google_mail** si quieren el correo del dominio en Gmail o Google Workspace. Pasá los pasos y los MX actuales. El SPF tiene que incluir el de REVISION ALPHA. No cambies el DNS ni inventes el TXT de verificación ni el DKIM: esos los muestra Google.
- **hosting_product_info** si piden información, precios, ficha o stack de un producto (hosting, WordPress, cloud, dedicados, Emailer u otro del sitio). Pegá los links, incluida la página de stack cuando venga. No inventes URLs ni importes.
- **hosting_unpaid_invoices** para facturas, también las últimas, las pagadas o «aunque estén pagas», el saldo pendiente o un link de pago. Pasá `limit` si piden un número, por ejemplo las últimas 3; si piden las pagadas o todas, usá 20. Pegá el listado entero, incluidas las cobradas, el saldo y los links de las que tengan saldo. No digas que solo podés mostrar las impagas, ni que no tenés acceso, ni los mandes a una web. No inventes importes, URLs, emails ni teléfonos. La deuda es de la persona, no de un solo dominio.
- **request_hosting_upgrade** si quieren cambiar de plan. Sin `plan`, lista los planes con el precio de Stripe y el paquete del servidor, y no cambia nada. Cuando el cliente elija uno, volvé a llamarlo con ese `plan` (y `domain` si hay varios). Eso cambia la suscripción en Stripe y el paquete en cPanel. No inventes un precio: usá solo el que devuelve la herramienta.

## Reglas
- {$alwaysData}
- El contacto puede tener varios dominios. No elijas uno por tu cuenta. Si la herramienta dice que hay más de uno, preguntá cuál y no cambies nada.
- Si preguntan si se puede activar y la cuenta no está suspendida, decí que el servidor sigue activo y ofrecé la clave, el correo o el FTP. No reactives la suscripción en Stripe y no inventes una dirección ni un teléfono.
- Si la herramienta dice que la cuenta está suspendida, repetí eso y no ofrezcas un contacto inventado.
- La clave, el correo y el FTP se envían a la persona que escribe en este chat. No elijas otro contacto.
- Si la herramienta dice que la clave ya se envió por WhatsApp o que quedó en este chat, confirmá en una frase y no repitas la contraseña.
- Si te pide pegar un mensaje, pegalo tal cual.
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
            [
                'module_key' => 'list60',
                'section_key' => 'alta',
                'section_label' => 'Lista de 60: quién entra',
                'order' => 24,
                'is_active' => true,
                'helper_text' => 'Revisar inbox (incluidos archivados) y decidir quién pasa a Lista 60. Routing key: list60:alta',
                'prompt_instruction' => List60OutreachPromptDefaults::altaInstruction(),
            ],
        ];
    }
}
