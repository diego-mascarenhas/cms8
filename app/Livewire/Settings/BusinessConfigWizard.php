<?php

namespace App\Livewire\Settings;

use App\Jobs\LoadTeamBusinessInsightsJob;
use App\Models\Prompt;
use App\Models\Team;
use App\Services\AssistantChatService;
use App\Services\AstralChartService;
use App\Services\BusinessCreationInsightsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;
use Livewire\Component;
use Livewire\WithFileUploads;

use function Laravel\Ai\agent;

class BusinessConfigWizard extends Component
{
    use WithFileUploads;

    public Team $team;

    public int $step = 1;

    /** @var array<string, mixed> */
    public array $config = [];

    public ?string $summary = null;

    public bool $summaryLoading = false;

    /** Last challenge text we sent to AI; skip reprocessing if unchanged. */
    public ?string $lastProcessedChallenge = null;

    /** @var array<string, mixed> */
    public array $insights = [];

    public bool $insightsLoading = false;

    /** Fase del proceso para el loader (solo landing la rellena). */
    public ?string $insightsPhase = null;

    /** @var \Illuminate\Http\UploadedFile|\Livewire\TemporaryUploadedFile|null */
    public $logo = null;

    public bool $showEmailRequired = false;

    public bool $isLandingWizard = false;

    protected function rules(): array
    {
        return [
            'logo' => 'nullable|image|max:2048',
        ];
    }

    public function updatedLogo(): void
    {
        $this->validateOnly('logo');
    }

    protected static array $configKeys = [
        'business_name', 'business_industry', 'business_location', 'business_postal_code',
        'business_phone', 'business_whatsapp', 'business_website', 'business_email',
        'contact_email',
        'business_tagline', 'business_description', 'business_challenge',
        'first_name', 'last_name', 'birth_date', 'birth_time', 'country', 'language',
        'address', 'landmark', 'pincode', 'city',
        'twitter', 'facebook', 'instagram', 'linkedin', 'youtube', 'tiktok',
        'whatsapp_url', 'telegram', 'pinterest', 'threads',
        'wants_to_deepen',
    ];

    public function mount(Team $team): void
    {
        Gate::authorize('update', $team);
        $this->team = $team;

        $saved = $team->getSetting('business_config', []);
        if (is_string($saved))
        {
            $saved = json_decode($saved, true) ?: [];
        }
        foreach (self::$configKeys as $key)
        {
            $this->config[$key] = $saved[$key] ?? ($key === 'language' ? '' : '');
        }
        if (is_array($this->config['language'] ?? null))
        {
            $this->config['language'] = $this->config['language'][0] ?? '';
        }
        if (! empty($saved['_insights']) && is_array($saved['_insights']))
        {
            $this->insights = $saved['_insights'];
        }
        if (isset($saved['_summary']) && is_string($saved['_summary']) && $saved['_summary'] !== '')
        {
            $this->summary = $saved['_summary'];
        }
    }

    public function nextStep(): void
    {
        $this->persistConfig();
        if ($this->step < 6)
        {
            $this->step++;
        }
    }

    public function previousStep(): void
    {
        $this->persistConfig();
        if ($this->step > 1)
        {
            $this->step--;
        }
    }

    public function goToStep(int $step): void
    {
        $this->persistConfig();
        if ($step >= 1 && $step <= 6)
        {
            $this->step = $step;
        }
    }

    protected function persistConfig(): void
    {
        $existing = $this->team->getSetting('business_config', []);
        if (is_string($existing))
        {
            $existing = json_decode($existing, true) ?: [];
        }
        if (! is_array($existing))
        {
            $existing = [];
        }

        $payload = $existing;

        foreach (self::$configKeys as $key)
        {
            $value = $this->config[$key] ?? null;
            if ($value !== null && $value !== '' && $value !== [])
            {
                $payload[$key] = is_array($value) ? $value : (string) $value;
            } else
            {
                unset($payload[$key]);
            }
        }

        if ($this->insights !== [])
        {
            $payload['_insights'] = $this->insights;
        }

        if ($this->summary !== null && $this->summary !== '')
        {
            $payload['_summary'] = $this->summary;
        }

        $this->team->setSetting('business_config', $payload, [
            'type' => 'json',
            'group' => 'business-config',
        ]);
    }

    public function setWantsToDeepen(string $value): void
    {
        if ($value !== 'si' && $value !== 'no')
        {
            return;
        }
        $this->config['wants_to_deepen'] = $value;
        $this->persistConfig();
    }

    public function submit(): void
    {
        $industry = trim((string) ($this->config['business_industry'] ?? ''));
        $description = trim((string) ($this->config['business_description'] ?? ''));
        $tagline = trim((string) ($this->config['business_tagline'] ?? ''));
        $canLoadInsights = $industry !== '' && $description !== '' && $tagline !== '';
        $hasReport = ! empty($this->insights['potential_clients_summary'] ?? null);

        if (! $hasReport && $canLoadInsights)
        {
            $this->queueTeamInsightsJob();
        } else
        {
            $this->persistConfig();
        }

        $this->dispatch('saved');
    }

    /**
     * Queue a new market report using the current wizard data (team settings only). Use after changing business fields.
     */
    public function regenerateMarketInsightsReport(): void
    {
        if ($this->insightsLoading)
        {
            return;
        }
        $industry = trim((string) ($this->config['business_industry'] ?? ''));
        $description = trim((string) ($this->config['business_description'] ?? ''));
        $tagline = trim((string) ($this->config['business_tagline'] ?? ''));
        if ($industry === '' || $description === '' || $tagline === '')
        {
            return;
        }
        $this->queueTeamInsightsJob();
    }

    /**
     * Persist form fields, mark request time, clear cached report and dispatch the same insights pipeline as landing (queue worker).
     */
    private function queueTeamInsightsJob(): void
    {
        $this->persistConfig();
        $this->team->refresh();
        $existing = $this->decodeTeamBusinessConfig();
        $existing['_insights_requested_at'] = now()->toIso8601String();
        unset($existing['_insights']);
        $this->team->setSetting('business_config', $existing, [
            'type' => 'json',
            'group' => 'business-config',
        ]);
        LoadTeamBusinessInsightsJob::dispatch($this->team->id);
        $this->insightsLoading = true;
        $this->insights = [];
        $this->insightsPhase = 'market_data';
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeTeamBusinessConfig(): array
    {
        $existing = $this->team->getSetting('business_config', []);
        if (is_string($existing))
        {
            return json_decode($existing, true) ?: [];
        }

        return is_array($existing) ? $existing : [];
    }

    /**
     * Step 6: sync _insights written by {@see LoadTeamBusinessInsightsJob} (or synchronous fallback after timeout).
     */
    public function checkInsightsReady(?BusinessCreationInsightsService $insightsService = null): void
    {
        if ($this->step !== 6)
        {
            return;
        }
        $insightsService ??= app(BusinessCreationInsightsService::class);
        $this->team->refresh();
        $config = $this->decodeTeamBusinessConfig();
        $this->insightsPhase = $config['_insights_phase'] ?? null;
        $insights = $config['_insights'] ?? null;
        if (! empty($insights) && is_array($insights))
        {
            $this->insights = $insights;
            $this->insightsLoading = false;
            $this->insightsPhase = null;
            unset($config['_insights_requested_at']);
            $this->team->setSetting('business_config', $config, [
                'type' => 'json',
                'group' => 'business-config',
            ]);
            $this->persistConfig();

            return;
        }

        $requestedAt = $config['_insights_requested_at'] ?? null;
        if (! is_string($requestedAt) || $requestedAt === '')
        {
            return;
        }

        try
        {
            $queuedForSeconds = Carbon::parse($requestedAt)->diffInSeconds(now());
        } catch (\Throwable)
        {
            return;
        }

        if ($queuedForSeconds < 45)
        {
            return;
        }

        try
        {
            $this->insights = $insightsService->runForTeam($this->team);
        } catch (\Throwable $e)
        {
            Log::warning('Team insights synchronous fallback failed', [
                'team_id' => $this->team->id,
                'error' => $e->getMessage(),
            ]);
            $this->insights = [
                'potential_clients_summary' => 'No se pudo generar el informe ahora. Intenta de nuevo en unos minutos.',
            ];
        }

        $this->insightsLoading = false;
        $this->insightsPhase = null;

        $this->team->refresh();
        $existing = $this->decodeTeamBusinessConfig();
        $existing['_insights'] = $this->insights;
        unset($existing['_insights_phase'], $existing['_insights_requested_at']);
        $this->team->setSetting('business_config', $existing, [
            'type' => 'json',
            'group' => 'business-config',
        ]);
        $this->persistConfig();
    }

    public function hydrate(): void
    {
        if ($this->step === 6 && $this->insightsLoading)
        {
            $this->checkInsightsReady();
        }
    }

    public function triggerSummaryIfChanged(AssistantChatService $assistant): void
    {
        if (trim((string) ($this->config['business_challenge'] ?? '')) === '')
        {
            return;
        }
        $this->generateSummary($assistant);
    }

    public function generateSummary(AssistantChatService $assistant): void
    {
        $challenge = trim((string) ($this->config['business_challenge'] ?? ''));
        if ($challenge !== '' && $challenge === $this->lastProcessedChallenge && $this->summary !== null)
        {
            $this->summaryLoading = false;

            return;
        }
        $this->summaryLoading = true;
        $this->summary = null;
        $contextParts = [];
        $contextParts[] = 'Datos del negocio:';
        $contextParts[] = '- Nombre: '.trim((string) ($this->config['business_name'] ?? ''));
        $contextParts[] = '- Rubro/Sector: '.trim((string) ($this->config['business_industry'] ?? ''));
        $contextParts[] = '- Ubicación: '.trim((string) ($this->config['business_location'] ?? ''));
        $contextParts[] = '- Código postal: '.trim((string) ($this->config['business_postal_code'] ?? ''));
        $contextParts[] = '- Teléfono: '.trim((string) ($this->config['business_phone'] ?? ''));
        $contextParts[] = '- WhatsApp: '.trim((string) ($this->config['business_whatsapp'] ?? ''));
        $contextParts[] = '- Página web: '.trim((string) ($this->config['business_website'] ?? ''));
        $contextParts[] = '- Email: '.trim((string) ($this->config['business_email'] ?? ''));
        $contextParts[] = '- Eslogan: '.trim((string) ($this->config['business_tagline'] ?? ''));
        $contextParts[] = '- Descripción: '.trim((string) ($this->config['business_description'] ?? ''));

        $birthDate = $this->config['birth_date'] ?? null;
        if ($birthDate)
        {
            try
            {
                $astral = new AstralChartService;
                $birthCarbon = Carbon::parse($birthDate);
                $zodiac = $astral->getZodiacSign($birthCarbon);
                $northNode = $astral->getNorthNode($birthCarbon);
                $contextParts[] = '';
                $contextParts[] = 'Arquetipo humano (fecha y hora de nacimiento):';
                $contextParts[] = '- Signo zodiacal: '.($zodiac['sign'] ?? '').' '.($zodiac['symbol'] ?? '').' ('.($zodiac['element'] ?? '').')';
                $contextParts[] = '- Nodo Norte: '.($northNode['north'] ?? '');
                $contextParts[] = '- Nodo Sur: '.($northNode['south'] ?? '');
                if (! empty($this->config['birth_time']))
                {
                    $contextParts[] = '- Hora de nacimiento: '.$this->config['birth_time'];
                }
            } catch (\Throwable $e)
            {
                Log::warning('AstralChartService in business summary', ['error' => $e->getMessage()]);
            }
        }

        $context = implode("\n", $contextParts);
        $userMessage = $challenge !== ''
            ? "Problemática actual del negocio:\n\n".$challenge."\n\n---\n\n".$context
            : $context;

        $prompt = Prompt::findByRoutingKey('landing');
        $teamId = $this->team->id;

        try
        {
            if ($prompt)
            {
                $result = $assistant->run($userMessage, $teamId, null, null, false, 'landing');
                $this->summary = $result['response'] ?? '';
            } else
            {
                $defaultInstruction = 'Eres un consultor de negocio. Con el contexto que te proporcionan (datos del negocio, problemática actual y arquetipo humano por fecha de nacimiento), genera un resumen muy conciso (máximo 1 párrafo corto o 3-5 puntos) de lo que esta empresa necesita para mejorar. Sé directo y práctico.';
                $agent = agent(
                    instructions: $defaultInstruction,
                    messages: [],
                    tools: [],
                );
                $response = $agent->prompt($userMessage, [], Lab::Anthropic);
                $this->summary = $response->text ?? '';
            }
        } catch (\Throwable $e)
        {
            Log::error('Business summary generation failed', ['error' => $e->getMessage()]);
            $this->summary = 'Error al generar el resumen. Intenta de nuevo.';
        }

        $this->lastProcessedChallenge = $challenge;
        $this->summaryLoading = false;
    }

    public function render()
    {
        return view('livewire.settings.business-config-wizard');
    }
}

