<?php

namespace App\Services;

use App\Enums\AutomationKind;
use App\Models\Automation;
use App\Models\Contact;
use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TeamSiteAssistantPromptService
{
    public const SETTING_KEY = 'assistant_default_prompt_key';

    public const OFF_KEY = '__off__';

    public const EMBED_SLUG = 'asistente-web';

    public const RECOMMENDED_SECTION_KEY = 'citas_y_ventas';

    public const WIDGET_SCRIPT = '/js/cms8-widgets.js';

    public const WIDGET_GLOBAL = 'CMS8_WIDGETS_API_BASE';

    public const WIDGET_DATA_ATTR = 'data-cms8-widget';

    /**
     * @return list<array{key: string, label: string, section_label: string, prompt_instruction: string, custom: bool}>
     */
    public function promptOptions(Team $team): array
    {
        $catalog = app(AssistantPromptCatalog::class);
        $hiddenSections = $this->ownBrandSectionKeys();
        $prompts = Prompt::forTeam((int) $team->id)
            ->active()
            ->with('module')
            ->orderBy('order')
            ->orderBy('section_label')
            ->get();

        $options = [];
        foreach ($prompts as $prompt)
        {
            if (in_array((string) $prompt->section_key, $hiddenSections, true))
            {
                continue;
            }

            $key = $this->routingKeyFor($prompt);
            $options[] = [
                'key' => $key,
                'label' => $prompt->section_label.' ('.$key.')',
                'section_label' => (string) $prompt->section_label,
                'prompt_instruction' => (string) $prompt->prompt_instruction,
                'custom' => ! $catalog->isSystemDefault($key, (string) $prompt->section_key),
            ];
        }

        return $options;
    }

    public function selectedRoutingKey(Team $team): ?string
    {
        $key = trim((string) $team->getSetting(self::SETTING_KEY, ''));

        return $key !== '' ? $key : null;
    }

    /**
     * Team default for chats on Automático (no pinned prompt): stay silent.
     * A contact can still pin a prompt and get replies.
     */
    public function isSilentDefault(Team $team): bool
    {
        return $this->selectedRoutingKey($team) === self::OFF_KEY;
    }

    public function resolvedRoutingKey(Team $team): ?string
    {
        $key = $this->selectedRoutingKey($team);
        if ($key === null || $key === self::OFF_KEY)
        {
            return null;
        }

        $prompt = Prompt::findByRoutingKey($key, (int) $team->id);
        if (! $prompt || ! $prompt->is_active || $prompt->isGeneralRouter())
        {
            return null;
        }

        return $key;
    }

    public function select(Team $team, ?string $routingKey): void
    {
        $key = $routingKey !== null ? trim($routingKey) : '';

        if ($key === '' || $key === self::OFF_KEY)
        {
            $team->setSetting(self::SETTING_KEY, $key, [
                'group' => 'chat',
                'type' => 'text',
                'is_encrypted' => false,
            ]);
            $this->syncEmbedAutomation($team, null);
            app(AssistantAutomationRunner::class)->releaseAwaitingSessionsForTeam((int) $team->id);

            return;
        }

        $prompt = Prompt::findByRoutingKey($key, (int) $team->id);
        if (! $prompt || ! $prompt->is_active)
        {
            throw new InvalidArgumentException(__('team_settings.site_assistant.invalid_prompt'));
        }

        $resolvedKey = $this->routingKeyFor($prompt);
        $team->setSetting(self::SETTING_KEY, $resolvedKey, [
            'group' => 'chat',
            'type' => 'text',
            'is_encrypted' => false,
        ]);
        $this->syncEmbedAutomation($team, $resolvedKey);
        app(AssistantAutomationRunner::class)->releaseAwaitingSessionsForTeam((int) $team->id);
    }

    public function create(Team $team, string $label, string $instruction): Prompt
    {
        $module = $this->resolveModule();
        if (! $module)
        {
            throw new InvalidArgumentException(__('team_settings.site_assistant.missing_module'));
        }

        $sectionKey = $this->uniqueSectionKey($team, $label);

        return Prompt::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => $sectionKey,
            'section_label' => trim($label),
            'prompt_instruction' => trim($instruction),
            'helper_text' => __('team_settings.site_assistant.helper_text'),
            'is_active' => true,
            'order' => 10,
        ]);
    }

    public function updateContent(Team $team, string $routingKey, string $label, string $instruction): Prompt
    {
        $prompt = Prompt::findByRoutingKey(trim($routingKey), (int) $team->id);
        if (! $prompt || ! $prompt->is_active)
        {
            throw new InvalidArgumentException(__('team_settings.site_assistant.invalid_prompt'));
        }

        $prompt->section_label = trim($label);
        $prompt->prompt_instruction = trim($instruction);
        $prompt->save();

        return $prompt;
    }

    public function deleteOwned(Team $team, string $routingKey): void
    {
        $key = trim($routingKey);
        if ($key === '' || $key === self::OFF_KEY)
        {
            throw new InvalidArgumentException(__('team_settings.site_assistant.invalid_prompt'));
        }

        $prompt = Prompt::findByRoutingKey($key, (int) $team->id);
        if (! $prompt || ! $prompt->is_active)
        {
            throw new InvalidArgumentException(__('team_settings.site_assistant.invalid_prompt'));
        }

        $resolvedKey = $this->routingKeyFor($prompt);
        if (app(AssistantPromptCatalog::class)->isSystemDefault($resolvedKey, (string) $prompt->section_key))
        {
            throw new InvalidArgumentException(__('team_settings.site_assistant.cannot_delete'));
        }

        $selected = $this->selectedRoutingKey($team);
        $wasSelected = $selected === $resolvedKey || $selected === $key;

        $prompt->delete();
        $this->clearPinnedPromptKey($team, $resolvedKey);
        if ($key !== $resolvedKey)
        {
            $this->clearPinnedPromptKey($team, $key);
        }

        if ($wasSelected)
        {
            $this->select($team, self::OFF_KEY);
        }
    }

    /**
     * @return array{
     *     selected_key: string|null,
     *     prompts: list<array{key: string, label: string, section_label: string, prompt_instruction: string, custom: bool}>,
     *     catalog: list<array{group: string, group_label: string, items: list<array{key: string, section_key: string, label: string, helper: string, section_label: string, prompt_instruction: string, own_brand: bool, owned: bool, drifted: bool}>}>,
     *     default_instruction: string,
     *     recommended_label: string,
     *     embed: array{snippet: string, api_base: string, script_url: string}
     * }
     */
    public function settingsPayload(Team $team): array
    {
        $automation = $this->syncEmbedAutomation($team, $this->resolvedRoutingKey($team));
        $apiBase = url('/api/embed/automation/'.$automation->public_token);

        return [
            'selected_key' => $this->selectedRoutingKey($team),
            'prompts' => $this->promptOptions($team),
            'catalog' => app(AssistantPromptCatalog::class)->groupsFor($team),
            'default_instruction' => $this->defaultInstruction(),
            'recommended_label' => __('team_settings.site_assistant.recommended_label'),
            'embed' => [
                'snippet' => (string) $this->embedSnippet($automation),
                'api_base' => $apiBase,
                'script_url' => url(self::WIDGET_SCRIPT),
            ],
        ];
    }

    public function embedAutomation(Team $team): ?Automation
    {
        return Automation::forTeam((int) $team->id)
            ->where('slug', self::EMBED_SLUG)
            ->first();
    }

    public function embedSnippet(?Automation $automation): ?string
    {
        if (! $automation || $automation->public_token === null || $automation->public_token === '')
        {
            return null;
        }

        $base = url('/api/embed/automation/'.$automation->public_token);
        $script = url(self::WIDGET_SCRIPT);
        $jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        return '<div '.self::WIDGET_DATA_ATTR.'="assistant"></div>'."\n"
            .'<script>'."\n"
            .'  window.'.self::WIDGET_GLOBAL.' = '.json_encode($base, $jsonFlags).';'."\n"
            .'</script>'."\n"
            .'<script src='.json_encode($script, $jsonFlags).' async></script>';
    }

    public function defaultInstruction(): string
    {
        return <<<'PROMPT'
# Flujo: citas, catálogo y ventas

Atendés a los clientes de este negocio en la web y en WhatsApp. Tenés dos objetivos: **llenar la agenda** y **cerrar pedidos**. Cada respuesta tiene que dejar al cliente un paso más cerca de uno de los dos.

## Primer mensaje

Un saludo suelto («hola», «buenas») no es una consulta: presentate en una frase y ofrecé las dos puertas concretas de este negocio, una cita o ver el catálogo. Nunca contestes un saludo genérico sin proponer nada.

## Citas

- Consultá la agenda real con list_calendar_events y check_calendar_availability.
- Ofrecé **dos o tres huecos concretos** en vez de preguntar «¿cuándo te viene bien?».
- El evento no es solo del agente: invitá a **quien la pide** (guest_contact_ids). Si no tiene email, pedilo y guardalo con update_contact antes de crear.
- Preguntá si quieren sumar a más personas. Para cada extra pedí **nombre, apellido y email**, create_contact si no están, y sumalos a guest_contact_ids.
- create_calendar_event recién cuando tengas horario + invitados con email. Confirmá solo si la herramienta devolvió el evento.
- Un «no» / «no gracias» a «¿agregás algo?» (notas, ubicación, más gente) **no cancela la cita**: creá el evento en ese turno sin extras. Solo no crees si el «no» responde a «¿agendo?» o «¿cancelo?».

## Catálogo y venta

- Mostrá productos con list_product_catalog o search_products: nombre y precio solo si la herramienta lo trajo, tres o cuatro opciones como mucho. No relistes el catálogo si solo confirman. Si piden la foto y has_image es true, **send_product_image**.
- Horarios, pagos, entrega y notas de la sucursal: **get_store_info**. No digas que no está en el sistema.
- En cuanto elige uno («sí», «dale», «agregame 2»), add_to_whatsapp_cart en ese mismo turno. Cuando confirman el pedido, **confirm_whatsapp_order**. Sin número de orden no digas que quedó registrado.
- Si no hay teléfono en contexto (asistente web sin destinatario), el carrito no aplica: pedile que escriba por WhatsApp. En WhatsApp no le pidas *comprar* más el nombre.

## Límites

- Precios, stock, plazos y disponibilidad salen solo de las herramientas. Si un dato no está, decilo en una frase y ofrecé la alternativa que sí exista.
- No prometas descuentos, envíos ni plazos que no figuren en los datos del negocio.
- Una sola pregunta por mensaje. Si no queda claro si quieren cita o comprar, preguntá eso y nada más.
PROMPT;
    }

    public function routingKeyFor(Prompt $prompt): string
    {
        $prompt->loadMissing('module');

        return $prompt->module
            ? $prompt->module->key.':'.$prompt->section_key
            : $prompt->section_key;
    }

    public function syncEmbedAutomation(Team $team, ?string $routingKey): Automation
    {
        $existing = Automation::withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->where('slug', self::EMBED_SLUG)
            ->first();

        $settings = is_array($existing?->settings) ? $existing->settings : [];
        if (! isset($settings['welcome_message']) || trim((string) $settings['welcome_message']) === '')
        {
            $settings['welcome_message'] = __('team_settings.site_assistant.welcome_message');
        }
        $settings['description'] = __('team_settings.site_assistant.embed_description');

        return Automation::withoutGlobalScope('team')->updateOrCreate(
            [
                'team_id' => $team->id,
                'slug' => self::EMBED_SLUG,
            ],
            [
                'name' => $existing?->name ?: __('team_settings.site_assistant.embed_name'),
                'kind' => AutomationKind::Action,
                'is_active' => true,
                'entry_prompt_key' => $routingKey,
                'channels' => Automation::normalizeChannels([
                    Automation::CHANNEL_HUMANO => true,
                    Automation::CHANNEL_CHAT => true,
                    Automation::CHANNEL_API => true,
                    Automation::CHANNEL_WHATSAPP => false,
                    Automation::CHANNEL_EMAIL => false,
                ]),
                'settings' => $settings,
                'public_token' => $existing?->public_token ?: bin2hex(random_bytes(32)),
            ],
        );
    }

    protected function resolveModule(): ?Module
    {
        foreach (['chat', 'prompts', 'contacts'] as $key)
        {
            $module = Module::query()->where('key', $key)->first();
            if ($module)
            {
                return $module;
            }
        }

        return Module::query()->orderBy('id')->first();
    }

    /**
     * @return list<string>
     */
    private function ownBrandSectionKeys(): array
    {
        return collect(app(AssistantPromptCatalog::class)->items())
            ->where('own_brand', true)
            ->pluck('section_key')
            ->map(fn ($key): string => (string) $key)
            ->values()
            ->all();
    }

    private function clearPinnedPromptKey(Team $team, string $routingKey): void
    {
        $contacts = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('data->chat_assistant_prompt_key', $routingKey)
            ->get();

        foreach ($contacts as $contact)
        {
            $data = $contact->data;
            $payload = is_array($data) ? $data : (array) $data;
            unset($payload['chat_assistant_prompt_key']);
            $contact->data = (object) $payload;
            $contact->save();
        }
    }

    protected function uniqueSectionKey(Team $team, string $label): string
    {
        $base = Str::slug(trim($label), '_');
        if ($base === '')
        {
            $base = self::RECOMMENDED_SECTION_KEY;
        }

        $key = $base;
        $suffix = 2;
        while (
            Prompt::forTeam((int) $team->id)
                ->where('section_key', $key)
                ->exists()
        ) {
            $key = $base.'_'.$suffix;
            $suffix++;
        }

        return $key;
    }
}
