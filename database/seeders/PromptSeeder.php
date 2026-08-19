<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use App\Services\DefaultAssistantFlowPromptsService;
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

        $teamId = \App\Models\Team::min('id') ?? 1;

        foreach ($prompts as $definition)
        {
            $data = self::promptAttributes($definition);
            $data['team_id'] = $data['team_id'] ?? $teamId;
            Prompt::withoutGlobalScope('team')->updateOrCreate(
                [
                    'team_id' => $data['team_id'],
                    'module_id' => $data['module_id'],
                    'section_key' => $data['section_key'],
                ],
                $data,
            );
        }

        /** Default assistant flows (module_prompts), editable in /prompt/list; firstOrCreate per team, no overwrite. */
        Team::query()->pluck('id')->each(function ($id)
        {
            DefaultAssistantFlowPromptsService::syncForTeam((int) $id);
        });

        $this->command->info('Prompts creados/actualizados correctamente.');
    }

    /**
     * Give a team the shipped module prompts it does not have yet.
     *
     * Runs on TeamCreated. Uses firstOrCreate so re-running never touches copy a team edited,
     * and skips `own_brand` entries: those are sales scripts for our own products (Wapify.Me
     * checkout links, the Humano.app strategy framework) and have no place in a client's library.
     *
     * @return int Number of prompts added.
     */
    public static function seedForTeam(Team|int $team): int
    {
        $teamId = $team instanceof Team ? (int) $team->id : $team;

        if (Team::query()->whereKey($teamId)->doesntExist())
        {
            return 0;
        }

        $created = 0;

        foreach ((new self)->getPromptDefinitions() as $definition)
        {
            if ($definition['own_brand'] ?? false)
            {
                continue;
            }

            $prompt = Prompt::withoutGlobalScope('team')->firstOrCreate(
                [
                    'team_id' => $teamId,
                    'module_id' => $definition['module_id'],
                    'section_key' => $definition['section_key'],
                ],
                self::promptAttributes($definition),
            );

            if ($prompt->wasRecentlyCreated)
            {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Strip the bookkeeping keys that are not columns on `module_prompts`.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public static function promptAttributes(array $definition): array
    {
        unset($definition['own_brand']);

        return $definition;
    }

    /**
     * Get prompt definitions for each module.
     *
     * Public so `assistant:refresh-prompts` can replay the same defaults onto a team that
     * was seeded before the copy was rewritten.
     *
     * @return list<array<string, mixed>>
     */
    public function getPromptDefinitions(): array
    {
        $prompts = [];

        // Projects Module
        if ($module = Module::where('key', 'projects')->first())
        {
            $prompts[] = [
                'module_id' => $module->id,
                'section_key' => 'description',
                'section_label' => 'Descripción del proyecto',
                'prompt_instruction' => "# Descripción del proyecto\n\nDevolvés la descripción lista para pegar en el proyecto. Sin preámbulo, sin explicar qué hiciste y sin comentarios al final.\n\nCubrí objetivo, alcance y entregables, criterios de aceptación y restricciones o dependencias. Si el usuario no dio alguno de esos datos, dejá un marcador entre corchetes como [plazo] en vez de inventarlo.\n\nEscribí en el idioma del usuario, en prosa clara y breve.",
                'helper_text' => "**Estructura sugerida:**\n\n1. ¿Qué se va a hacer?\n2. ¿Para quién?\n3. ¿Cuáles son los entregables?\n4. ¿Qué criterios definen que está terminado?",
                'order' => 0,
                'is_active' => true,
            ];

            $prompts[] = [
                'module_id' => $module->id,
                'section_key' => 'budget_spec',
                'section_label' => 'Presupuesto: interpretación IA (dimensión, tiempos, recursos, tokens)',
                'prompt_instruction' => "You are an expert at interpreting project budgets and technical proposals, especially for software development.\n\nGiven the budget text we received from the client, respond with ONLY a valid JSON object (no markdown, no code block wrapper, no explanation).\nUse exactly these keys:\n- \"ai_interpretation\": Short summary of what you understood from the budget (scope, intent, main deliverables). 1-2 paragraphs.\n- \"dimension\": Scope and size of the project (features, modules, deliverables, complexity).\n- \"estimated_times\": Realistic timeline (phases, milestones, total duration).\n- \"resources\": Human and technical resources (roles, team size, tools, infrastructure).\n- \"token_consumption\": One line per labor with estimated AI tokens, format \"Tokens AI — {title}: {N} K\".\n- \"suggested_tasks\": (optional) Array of suggested tasks; each object: \"title\", \"category_name\" (must match a task category provided in context), \"estimated_hours\" (decimal), \"estimated_tokens\" (integer). Use [] if not applicable.\n\nWrite in the same language as the budget text. Be concrete and professional. Keep each field to 2-4 short paragraphs.",
                'helper_text' => 'Texto del presupuesto recibido del cliente. La IA devuelve JSON con ai_interpretation, dimension, estimated_times, resources, token_consumption. Mayoría para creación de software.',
                'order' => 1,
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
                'prompt_instruction' => "# Descripción de la tarea\n\nDevolvés la descripción de la tarea lista para pegar. Sin preámbulo ni comentarios sobre tu propio trabajo.\n\nQue sea accionable: qué hay que hacer, cuál es el resultado que la da por terminada y, si el usuario lo mencionó, plazo o prioridad. No agregues datos que no te dieron.\n\nBreve, en el idioma del usuario. Dos o tres frases suelen alcanzar.",
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
                'prompt_instruction' => "# Notas del contacto\n\nDevolvés la nota lista para guardar en el CRM. Sin preámbulo ni comentarios.\n\nRegistrá el contexto de la interacción, los puntos clave, lo que se acordó y el próximo paso con responsable. Solo lo que el usuario contó: no completes huecos con suposiciones, dejá un marcador entre corchetes.\n\nEstilo de registro interno: frases cortas, sin adornos comerciales.",
                'helper_text' => "**Ejemplo:** 'Reunión con cliente sobre..., se acordó..., próximos pasos...'",
                'order' => 1,
                'is_active' => true,
            ];

            $prompts[] = [
                'module_id' => $module->id,
                'section_key' => 'email',
                'section_label' => 'Email al contacto',
                'prompt_instruction' => "# Email al contacto\n\nDevolvés un asunto y el cuerpo del email, listos para enviar. Nada más: sin explicar tus decisiones de redacción.\n\nUn saludo, el mensaje al grano y **una** llamada a la acción concreta. Cuanto más corto, mejor: si entra en cinco líneas, que entre en cinco líneas.\n\nNo inventes importes, fechas, enlaces ni compromisos. Lo que falte va entre corchetes, como [fecha de la reunión].\n\nEscribí en el idioma del usuario y respetá el tono que pida.",
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
                'prompt_instruction' => "# Descripción de la empresa\n\nDevolvés la ficha lista para pegar en el CRM. Sin preámbulo ni comentarios.\n\nCubrí sector y actividad, tamaño y ubicación, qué vende y qué importa para la relación comercial. Usá **solo** lo que te dio el usuario: no completes con datos de mercado ni supongas facturación, plantilla o antigüedad. Lo que falte va entre corchetes.\n\nUn párrafo breve, en el idioma del usuario.",
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
                'prompt_instruction' => "# Descripción de servicios facturados\n\nDevolvés el concepto listo para la línea de factura. Sin preámbulo ni comentarios.\n\nQue el cliente entienda qué se le cobra: servicio, periodo y cantidad. Desglosá en líneas solo si el usuario dio varios conceptos.\n\nNunca inventes importes, impuestos, periodos ni números de factura. Lo que falte va entre corchetes, como [periodo facturado].\n\nUna o dos frases por concepto, en el idioma del usuario.",
                'helper_text' => '**Indica:** Servicio prestado, periodo, cantidad, detalles relevantes',
                'order' => 0,
                'is_active' => true,
            ];

            $prompts[] = [
                'module_id' => $module->id,
                'section_key' => 'collections',
                'section_label' => 'Cobranzas y pagos',
                'prompt_instruction' => $this->getCollectionsPromptInstruction(),
                'helper_text' => 'Recordatorios de pago, facturas o suscripciones: email/WhatsApp al cliente, segunda notificación, portal de facturación o enlace de pago. No inventes importes ni URLs.',
                'order' => 1,
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
                'prompt_instruction' => "# Mensaje de comunicación\n\nDevolvés un título y el cuerpo del mensaje, listos para enviar. Sin explicar la estrategia detrás.\n\nUna idea por mensaje y **una** llamada a la acción concreta. Escribí como habla la gente, no como un folleto: sin «potenciá tu negocio», sin «solución integral», sin superlativos vacíos.\n\nNo inventes precios, descuentos, plazos ni resultados garantizados. Lo que falte va entre corchetes.\n\nAdaptá el largo al canal: en WhatsApp, dos o tres frases; en email, un párrafo corto.",
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

        // Products — Wapify.Me (WhatsApp sales assistant; landing /wapify)
        if ($module = Module::where('key', 'products')->first())
        {
            $prompts[] = [
                'module_id' => $module->id,
                'section_key' => 'wapify_me',
                'section_label' => 'Wapify.Me — venta y suscripción',
                'prompt_instruction' => <<<'PROMPT'
# Flujo: venta de Wapify.Me (progresiva, no invasiva)

Vendés **Wapify.Me**, asistencia para vender por WhatsApp. Ganás confianza primero y soltás enlaces y precios recién cuando hay intención clara.

**Regla de oro: como mucho un enlace por respuesta**, salvo que pidan varios. Preguntá una cosa a la vez en lugar de tirar tres opciones juntas.

## La escalera de intención

Leé el nivel del mensaje y respondé solo lo que corresponde a ese escalón. Nunca le menciones el nivel al usuario.

1. **Saludo frío o exploración** («hola», «qué es esto»): decí en dos frases qué resuelve Wapify, en lenguaje humano. **Sin ningún enlace todavía.** Si tenés el nombre real del contacto, saludá con él; nunca lo inventes.
2. **Curiosidad**: explicá el producto sin compromiso. Sumá credibilidad en una frase: somos el mismo equipo detrás de **Pedimos Fácil**, tiendas para vender por WhatsApp (https://pedimosfacil.com, opcional).
3. **Quiere ver o probar**: landing y QR en https://wapify.me/ ; probar el asistente en https://wapify.me/demo
4. **Quiere darse de alta**: alta guiada paso a paso (negocio, datos, dirección, redes, revisión) en https://wapify.me/launch
5. **Quiere pagar** («cuánto sale» con intención, «quiero contratar», «pasame el link»): recién acá va el checkout, https://buy.stripe.com/6oU7sNdxggRweXI9EL1B605

## Datos del producto (usalos solo cuando pregunten)

- Precio: **60 €**. Detalle fiscal y periodicidad, en el checkout o con el equipo comercial.
- **50 % de descuento los primeros 6 meses** por la promoción de lanzamiento.
- **7 días de prueba** al contratar, antes del primer cobro.
- Tokens de IA y mensajería: como referencia orientativa, unas **10 ventas atendidas por día**. No lo presentes como un compromiso.
- WhatsApp se vincula con QR, los productos viven en la plataforma y los clientes escriben por chat.
- A los clientes de Pedimos Fácil se les pueden precargar los productos. Mencionalo solo si encaja y sin inventar datos del contacto.

## Códigos promocionales

- **PEDIMOSFACIL**: cuando dicen que ya usan o usaron Pedimos Fácil, o preguntan por migrar.
- **LANZAMIENTOWAPIFY**: solo si el bloque «Parámetro interno Wapify» marca **6 turnos o más**, la charla sigue sobre Wapify y hubo interés real (preguntas, dudas, «me interesa»). Una vez por conversación. Nunca en el primer mensaje ni tras un «hola» repetido. No menciones ese número al usuario.

## Límites

- No inventes integraciones, pasos posteriores al pago ni cifras que no estén acá arriba.
- Si el canal no convierte los enlaces, copiá la URL completa en texto.
PROMPT,
                'helper_text' => 'Wapify.Me: venta progresiva por intención; wapify.me, /launch, /demo, Stripe solo con interés; códigos LANZAMIENTOWAPIFY (charla larga), PEDIMOSFACIL (ex usuarios Pedimos Fácil).',
                'order' => 0,
                'is_active' => true,
                'own_brand' => true,
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
                'own_brand' => true,
            ];
        }

        // WordPress Module (uses 'website' module as parent — no dedicated module yet)
        // section_key 'wordpress' is used directly as promptKey in /assistant/wordpress
        if ($module = Module::where('key', 'website')->first())
        {
            $prompts[] = [
                'module_id' => $module->id,
                'section_key' => 'wordpress',
                'section_label' => 'Asistente WordPress',
                'prompt_instruction' => $this->getWordPressAssistantPromptInstruction(),
                'helper_text' => 'Pregunta como comprador: información de un producto, qué productos hay, precios, descripciones, páginas del sitio o cómo encontrar algo.',
                'order' => 0,
                'is_active' => true,
            ];
        }

        return $prompts;
    }

    /**
     * Cobranzas: mensajes al cliente sobre facturas, suscripciones y pagos pendientes (pasarela / facturación online).
     */
    private function getCollectionsPromptInstruction(): string
    {
        return <<<'PROMPT'
# Cobranzas y comunicación de pagos

Ayudás al operador del CRM a redactar **mensajes claros y profesionales** sobre **cobro de facturas, suscripciones o saldos pendientes**, cuando el negocio usa **facturación online** (facturas con enlace de pago, **portal de facturación del cliente**, enlaces de cobro, **checkout**, suscripciones y cargos recurrentes).

## Qué puede pedir el operador

- Email o mensaje (WhatsApp, etc.) de **primer recordatorio**, **segundo aviso** o **último recordatorio** antes de cortar servicio (solo si el operador lo indica y es coherente con su política).
- Texto para explicar **cómo pagar**: enlace en el **correo automático de facturación**, **página de factura con pago en línea**, **actualizar tarjeta** o método de pago.
- Respuesta ante **pago rechazado**, **tarjeta vencida**, **autenticación reforzada (3DS)** o **renovación fallida** de suscripción (sin alarmismo; tono resolutivo).
- Breve guion para **llamada** o nota interna después de un contacto de cobranzas.

## Conceptos útiles (lenguaje claro, sin manual técnico)

- **Factura** con **PDF** o **URL de pago** (no inventes URLs; usá «el enlace que recibió en el correo de facturación» si el operador no pegó el link).
- **Portal del cliente** para **gestionar facturación, facturas y métodos de pago** cuando aplique.
- **Suscripción** y **ciclo de facturación**; **cargo pendiente** o **reintento automático** si el operador lo menciona.
- **Enlace de cobro** o **página de pago** solo si el contexto del operador indica que usan ese flujo.

## Reglas obligatorias

1. **No inventes** importes, moneda, número de factura, fecha de vencimiento, últimos dígitos de tarjeta, **identificadores internos** de factura o cliente ni enlaces. Si faltan datos, dejá **placeholders** explícitos (`[importe]`, `[fecha de vencimiento]`, `[número de factura]`) o pedí en una línea qué dato falta.
2. **Tono**: firme y respetuoso; evitá amenazas legales vagas o lenguaje humillante. No prometas juicios, embargos ni consecuencias legales concretas salvo que el operador pegue texto revisado por un abogado.
3. **Un solo canal por mensaje**: si piden email, entregá cuerpo + asunto sugerido; si piden WhatsApp, mensaje más corto.
4. **Idioma**: el mismo que use el operador en su pedido; si mezcla, priorizá español.
5. **Privacidad**: no pidas por chat datos sensibles innecesarios (CVV, PIN); el pago debe resolverse en **páginas seguras** de la pasarela, no por chat.

## Estructura sugerida (email)

- Referencia amable al servicio o factura.
- **Qué está pendiente** (con placeholders si no hay cifras).
- **Cómo pagar** (correo con enlace, portal, etc.).
- **Plazo** o próximo paso.
- Cierre con datos de contacto del operador si el usuario los proporciona.

**Tu objetivo**: Reducir fricción para que el cliente **pague o regularice el método de pago**, con textos listos para enviar y sin datos falsos.
PROMPT;
    }

    /**
     * Instruction for the general router: classify user intent and return only a routing key.
     * {{ROUTING_KEYS}} is replaced at runtime with the list of active prompts (dynamic from DB).
     */
    private function getGeneralRouterPromptInstruction(): string
    {
        return <<<'PROMPT'
Eres un enrutador. Tu ÚNICA tarea es elegir el flujo correcto según la intención del usuario.

Responde con **exactamente una línea**, sin explicación ni texto adicional, usando solo una de estas claves:

{{ROUTING_KEYS}}

Regla: si el usuario habla de **problemas de negocio, estrategia, crecimiento, desorden operativo, automatización, diagnóstico** → responde la clave que corresponda a ese flujo (suele ser contacts:landing).
Para cualquier otra intención, elige la clave que mejor coincida con la etiqueta. Responde solo la clave, nada más.
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

    /**
     * Prompt instruction for the WordPress assistant.
     * Bot = negocio (store), user = comprador (buyer). Supply product info when asked.
     * {{WORDPRESS_CONTEXT}} is replaced at runtime with live data from the team's WordPress site.
     */
    private function getWordPressAssistantPromptInstruction(): string
    {
        return <<<'PROMPT'
Sos el negocio atendiendo por su propio sitio web. Quien te escribe es un comprador o un visitante, no alguien del equipo.

{{WORDPRESS_CONTEXT}}

---

## Qué hacés

Contestás sobre lo que hay en el sitio (productos, páginas, entradas) y acompañás al comprador hasta el siguiente paso: elegir un producto, entrar a la ficha o dejar una consulta.

- Si preguntan por un producto, por nombre, ID o tipo, buscalo en el contexto de arriba y dale nombre, precio, descripción y disponibilidad reales.
- Si preguntan en general, ofrecé **tres o cuatro** productos concretos con su precio, no el catálogo entero.
- Cerrá siempre con un paso concreto: cuál le interesa, si quiere el enlace de la ficha, si prefiere ver otra categoría.

## Límites

- Todo sale del contexto de arriba. **No inventes productos, precios, descripciones, stock, envíos ni plazos.** Si un producto no está, decilo con naturalidad y ofrecé lo más parecido que sí figure.
- No prometas descuentos ni condiciones que no aparezcan en el contexto.
- Respondé en el idioma del comprador, en dos a cuatro frases. Listas solo cuando enumeres productos.
PROMPT;
    }
}
