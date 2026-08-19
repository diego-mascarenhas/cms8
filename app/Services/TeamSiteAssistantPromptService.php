<?php

namespace App\Services;

use App\Enums\AutomationKind;
use App\Models\Automation;
use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TeamSiteAssistantPromptService
{
    public const SETTING_KEY = 'assistant_default_prompt_key';

    public const EMBED_SLUG = 'asistente-web';

    public const RECOMMENDED_SECTION_KEY = 'citas_y_ventas';

    public const WIDGET_SCRIPT = '/js/cms8-widgets.js';

    public const WIDGET_GLOBAL = 'CMS8_WIDGETS_API_BASE';

    public const WIDGET_DATA_ATTR = 'data-cms8-widget';

    /**
     * @return list<array{key: string, label: string, section_label: string, prompt_instruction: string}>
     */
    public function promptOptions(Team $team): array
    {
        $prompts = Prompt::forTeam((int) $team->id)
            ->active()
            ->with('module')
            ->orderBy('order')
            ->orderBy('section_label')
            ->get();

        $options = [];
        foreach ($prompts as $prompt)
        {
            $key = $this->routingKeyFor($prompt);
            $options[] = [
                'key' => $key,
                'label' => $prompt->section_label.' ('.$key.')',
                'section_label' => (string) $prompt->section_label,
                'prompt_instruction' => (string) $prompt->prompt_instruction,
            ];
        }

        return $options;
    }

    public function selectedRoutingKey(Team $team): ?string
    {
        $key = trim((string) $team->getSetting(self::SETTING_KEY, ''));

        return $key !== '' ? $key : null;
    }

    public function resolvedRoutingKey(Team $team): ?string
    {
        $key = $this->selectedRoutingKey($team);
        if ($key === null)
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

        if ($key === '')
        {
            $team->setSetting(self::SETTING_KEY, '', [
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
        $prompt = Prompt::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => $sectionKey,
            'section_label' => trim($label),
            'prompt_instruction' => trim($instruction),
            'helper_text' => __('team_settings.site_assistant.helper_text'),
            'is_active' => true,
            'order' => 10,
        ]);

        $this->select($team, $this->routingKeyFor($prompt->load('module')));

        return $prompt;
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

        $this->select($team, $this->routingKeyFor($prompt->load('module')));

        return $prompt;
    }

    /**
     * @return array{
     *     selected_key: string|null,
     *     prompts: list<array{key: string, label: string, section_label: string, prompt_instruction: string}>,
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

Sos el asistente de este negocio. Ayudá a:

1. **Reservar citas** con la agenda real del equipo.
2. **Mostrar el catálogo** y buscar productos.
3. **Acompañar la compra** cuando el cliente elige un producto.

## Citas
- Usá list_calendar_events, check_calendar_availability, create_calendar_event.
- Confirmá fecha y hora con datos reales. No inventes disponibilidad.

## Catálogo y venta
- Usá list_product_catalog, search_products, add_to_whatsapp_cart.
- Precios solo de las herramientas. Si falta un dato, decilo.
- En la web, el carrito de WhatsApp aplica cuando hay un teléfono de cliente.

## Reglas
- Preguntá una cosa a la vez.
- Si no está claro si quieren cita o comprar, preguntá.
- No prometas descuentos ni plazos que no existan en los datos.
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
