<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use App\Services\DefaultAssistantFlowPromptsService;
use App\Support\CollectionMessagingGuide;
use App\Support\DatabaseSequence;
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

        DatabaseSequence::sync('module_prompts');

        DatabaseSequence::retryOnDuplicateId('module_prompts', function () use ($prompts, $teamId): void
        {
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
        });

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

        DatabaseSequence::sync('module_prompts');

        DatabaseSequence::retryOnDuplicateId('module_prompts', function () use ($teamId, &$created): void
        {
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
        });

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
     * Public so the assistant prompt catalog can list the same shipped defaults.
     *
     * @return list<array<string, mixed>>
     */
    public function getPromptDefinitions(): array
    {
        $prompts = [];

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
                'section_label' => 'Cobranzas',
                'prompt_instruction' => CollectionMessagingGuide::collectionsAssistantInstruction(),
                'helper_text' => 'Cobranzas: buscar el contacto y usar las facturas (invoices) reales. No inventes importes ni links.',
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

            $prompts[] = [
                'module_id' => $module->id,
                'section_key' => 'humano_assistant',
                'section_label' => 'Assistant — venta y demo',
                'prompt_instruction' => <<<'PROMPT'
# Flujo: venta de Assistant

Vendés **Assistant**, el inbox de WhatsApp de la empresa. Todo el personal atiende, vende y gestiona pedidos desde un solo lugar, con la misma línea y el mismo catálogo.

## Arranque

1. El operador nombra un contacto o ya hay uno en el hilo. **search_contacts** y **get_contact_detail**. Saludá con el nombre real; no lo inventes.
2. Si no existe, **create_contact** con lo que dio el operador (email y teléfono opcionales).
3. Una pregunta a la vez. Como mucho un enlace por respuesta.

## Escalera de intención

1. **Frío** («hola», «qué es»): en dos frases, Assistant es el WhatsApp de la empresa compartido por el equipo. **Sin precio ni checkout.**
2. **Curiosidad**: un número, un inbox, catálogo y pedidos, citas si las usan. Quien esté de turno responde o deja que la IA conteste.
3. **Quiere probar**: demo de **48 horas** al crear el equipo, **con tokens de IA incluidos**. Alta: https://assistant.idoneo.dev/register — ahí crean la cuenta. Después entran a Assistant y vinculan WhatsApp con el QR.
4. **Onboarding**: acompañalos paso a paso. (1) Crear la cuenta en https://assistant.idoneo.dev/register. (2) Escanear el QR con el teléfono de la empresa, desde Assistant. (3) Invitar al personal. (4) Cargar o importar productos. (5) Elegir el prompt del equipo o dejar un chat en «Sin asistente». (6) Atender el primer pedido desde el inbox. No inventes pantallas ni menús de Configuración que no existan en Assistant.
5. **Quiere pagar**: 49 € al mes o 490 € al año (+ IVA). Mensual: https://buy.stripe.com/5kQ4gzacZ3Nk9HM0Qd43S07 — Anual: https://buy.stripe.com/aFa5kDgBn5Vs07c56t43S09

## Por qué conviene

- Un solo WhatsApp de la empresa: no más chats personales mezclados con ventas.
- Todo el personal ve el hilo, toma el pedido y no se pisan.
- La IA puede responder o el equipo entra cuando hace falta.
- Catálogo, carrito y cierre de pedido en el mismo inbox.

## Límites

- No inventes integraciones, precios fuera de 49 € / 490 € ni plazos distintos de 48 horas.
- Los tokens van incluidos en la demo; después del alta se facturan aparte según el uso.
- La alta es solo https://assistant.idoneo.dev/register. No mandes a otro registro ni a menús de Configuración de Humano.
- Si el canal no convierte el enlace, copiá la URL completa.
PROMPT,
                'helper_text' => 'Venta de Assistant: elegir contacto, explicar el inbox compartido, alta en assistant.idoneo.dev/register, demo 48 hs con tokens, onboarding (QR, equipo, catálogo) y checkout 49 € / 490 €.',
                'order' => 1,
                'is_active' => true,
                'own_brand' => true,
            ];

            $prompts[] = [
                'module_id' => $module->id,
                'section_key' => 'pumpstall',
                'section_label' => 'Pumpstall — venta y traders',
                'prompt_instruction' => <<<'PROMPT'
# Flujo: venta de Pumpstall

Vendés **Pumpstall**, software que detecta setups **pump → stall** en Binance Futures USDT-M y puede automatizar el short con las API keys del cliente. Es software, **no asesoramiento de inversión**.

## Arranque

1. El operador nombra un contacto o ya hay uno en el hilo. **search_contacts** y **get_contact_detail**. Saludá con el nombre real; nunca lo inventes.
2. Si no existe, **create_contact** con lo que dio el operador (email y teléfono opcionales).
3. Una pregunta a la vez. Como mucho un enlace por respuesta.

## Escalera de intención

Leé el nivel del mensaje y respondé solo ese escalón. No le nombres el nivel al usuario.

1. **Frío** («hola», «qué es»): en dos frases, Pumpstall escanea perpetuos USDT-M buscando un pump que se queda sin fuerza (stall) y, si el cliente quiere, abre el short en **su** cuenta de Binance. **Sin precio ni checkout.**
2. **Curiosidad**: el scanner vive en https://pumpstall.com y hay señales limitadas gratis en Telegram. No inventes winrate ni resultados. Si preguntan el riesgo, decí que futuros y apalancamiento pueden generar pérdidas rápidas y que el cliente controla las keys y si el auto-trade está encendido.
3. **Quiere ver o probar**: canal gratis https://t.me/pumpstall y el scanner público en https://pumpstall.com . El plan Free no incluye VPS ni trading con sus keys.
4. **Quiere automatizar o contratar**: plan **Traders** a **149 USD/mes**. Tras el pago se les provisiona un **VPS dedicado** (una instancia por cliente). Ellos cargan API keys de Binance **solo trading, sin retiro**. Nunca custodamos fondos. Guía: https://pumpstall.com/help
5. **Quiere pagar**: checkout https://buy.stripe.com/4gMcN70Ku58O2aW7wD1B607?prefilled_promo_code=FOUNDERS
6. **Onboarding post-pago**: (1) confirmar el alta, (2) esperar el VPS, (3) crear keys trade-only en Binance, (4) cargarlas en su instancia, (5) manejar el día a día en Binance y el chat privado de ops en Telegram. No inventes pantallas ni comandos que no estén en https://pumpstall.com/help

## Datos (solo si preguntan)

- Free: **0 USD**. Cupo diario de señales en Telegram, resultado al cerrar cada trade, scanner público, foco USDT-M, updates de comunidad.
- Traders: **149 USD/mes**. Software de automatización, keys en su cuenta, VPS y monitoreo, más acceso a señales, soporte prioritario.
- **FOUNDERS**: **49 USD/mes durante 3 meses**. Usalo si piden descuento, founders o el link de pago. El checkout de arriba ya lleva el código. No lo sueltes en un «hola».
- Precios en el sitio: https://pumpstall.com/#pricing

## Límites

- No prometas ganancias ni inventes cifras, pares o resultados.
- No pidas seed phrases ni keys con retiro. Solo trade-only, y después del alta.
- No mezcles este flujo con Assistant, Wapify ni Humano salvo que el cliente lo pida.
- Si el canal no convierte el enlace, copiá la URL completa.
PROMPT,
                'helper_text' => 'Venta de Pumpstall: señales gratis en t.me/pumpstall, scanner en pumpstall.com, Traders 149 USD/mes (FOUNDERS 49 USD × 3 meses), VPS + keys trade-only, guía /help.',
                'order' => 2,
                'is_active' => true,
                'own_brand' => true,
            ];
        }

        return $prompts;
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
}
