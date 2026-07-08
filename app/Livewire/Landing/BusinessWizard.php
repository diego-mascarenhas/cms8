<?php

namespace App\Livewire\Landing;

use App\Jobs\GenerateBusinessSummaryJob;
use App\Jobs\LoadBusinessCreationInsightsJob;
use App\Mail\BusinessCreationReportMail;
use App\Models\BusinessCreationAiLog;
use App\Models\BusinessCreationSession;
use App\Models\Prompt;
use App\Services\AssistantChatService;
use App\Services\AstralChartService;
use App\Services\BusinessCreationInsightsService;
use App\Support\AiTasks;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

use function Laravel\Ai\agent;

class BusinessWizard extends Component
{
    use WithFileUploads;

    public ?string $token = null;

    public ?BusinessCreationSession $session = null;

    public int $step = 1;

    /** @var array<string, mixed> */
    public array $config = [];

    public ?string $summary = null;

    public bool $summaryLoading = false;

    /** @var array<string, mixed> */
    public array $insights = [];

    public bool $insightsLoading = false;

    /** Fase actual del proceso (apollo, web, ai) para el subtítulo del loader. Solo landing. */
    public ?string $insightsPhase = null;

    public bool $finalFlowRequested = false;

    public ?string $finalFlowPhase = null;

    /** @var \Illuminate\Http\UploadedFile|\Livewire\TemporaryUploadedFile|null */
    public $logo = null;

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

    public bool $showEmailRequired = false;

    public bool $isLandingWizard = true;

    public bool $reportSent = false;

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

    public function mount(?string $token = null): void
    {
        if ($token !== null)
        {
            $this->session = BusinessCreationSession::where('token', $token)->first();
            if (! $this->session)
            {
                $this->session = BusinessCreationSession::createWithToken();
                $this->redirect(route('landing.business-creation', ['token' => $this->session->token]), navigate: true);

                return;
            }
        } else
        {
            $this->session = BusinessCreationSession::createWithToken();
            $this->redirect(route('landing.business-creation', ['token' => $this->session->token]), navigate: true);

            return;
        }

        $this->token = $token;
        $this->step = (int) $this->session->current_step;
        $this->reportSent = $this->session->completed_at !== null;
        $saved = $this->session->config ?? [];
        foreach (self::$configKeys as $key)
        {
            $this->config[$key] = $saved[$key] ?? ($key === 'language' ? '' : '');
        }
        if (is_array($this->config['language'] ?? null))
        {
            $this->config['language'] = $this->config['language'][0] ?? '';
        }
        $challenge = trim((string) ($this->config['business_challenge'] ?? ''));
        $hash = $challenge !== '' ? hash('sha256', $challenge) : '';
        if ($hash !== '' && ($saved['_summary_challenge_hash'] ?? '') === $hash && isset($saved['_summary']) && $saved['_summary'] !== '')
        {
            $this->summary = $saved['_summary'];
        }
        if (! empty($saved['_insights']) && is_array($saved['_insights']))
        {
            $this->insights = $saved['_insights'];
        }
        if ($this->step === 6 && ! empty($saved['_insights_phase']) && empty($saved['_insights']))
        {
            $this->insightsLoading = true;
            $this->insightsPhase = $saved['_insights_phase'];
        }
        if (empty($saved['_step_history']))
        {
            $this->persistConfigWithStepEntry();
        }
    }

    public function nextStep(): void
    {
        $this->persistConfig();
        if ($this->step < 6)
        {
            $this->step++;
            $this->persistConfigWithStepEntry();
        }
    }

    public function previousStep(): void
    {
        $this->persistConfig();
        if ($this->step > 1)
        {
            $this->step--;
            $this->persistConfigWithStepEntry();
        }
    }

    public function goToStep(int $step): void
    {
        $this->persistConfig();
        if ($step >= 1 && $step <= 6)
        {
            $this->step = $step;
            $this->persistConfigWithStepEntry();
            if ($step === 5 && $this->session)
            {
                $this->syncSummaryFromSession();
            }
        }
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

    protected function persistConfig(): void
    {
        if (! $this->session)
        {
            return;
        }
        $payload = [];
        foreach (self::$configKeys as $key)
        {
            $value = $this->config[$key] ?? null;
            if ($value !== null && $value !== '' && $value !== [])
            {
                $payload[$key] = is_array($value) ? $value : (string) $value;
            }
        }
        $existing = $this->session->fresh()->config ?? [];
        foreach (['_summary', '_summary_challenge_hash', '_insights', '_insights_phase', '_insights_requested_at', '_step_history'] as $internal)
        {
            if (array_key_exists($internal, $existing))
            {
                $payload[$internal] = $existing[$internal];
            }
        }
        $this->session->update([
            'config' => $payload,
            'current_step' => $this->step,
        ]);
    }

    protected function persistConfigWithStepEntry(): void
    {
        if (! $this->session)
        {
            return;
        }
        $existing = $this->session->fresh()->config ?? [];
        $history = $existing['_step_history'] ?? [];
        $history[] = [
            'step' => $this->step,
            'at' => now()->toIso8601String(),
        ];
        $payload = $existing;
        $payload['_step_history'] = $history;
        $this->session->update([
            'config' => $payload,
            'current_step' => $this->step,
        ]);
    }

    /**
     * Load cached summary from session when entering step 5 (Desafío) so the UI shows the cached resumen.
     */
    protected function syncSummaryFromSession(): void
    {
        $saved = $this->session->fresh()->config ?? [];
        $challenge = trim((string) ($this->config['business_challenge'] ?? ''));
        $hash = $challenge !== '' ? hash('sha256', $challenge) : '';
        if ($hash !== '' && ($saved['_summary_challenge_hash'] ?? '') === $hash && isset($saved['_summary']) && $saved['_summary'] !== '')
        {
            $this->summary = $saved['_summary'];
            $this->summaryLoading = false;
        }
    }

    public function triggerSummaryIfChanged(): void
    {
        if (trim((string) ($this->config['business_challenge'] ?? '')) === '')
        {
            return;
        }
        if (! $this->session)
        {
            return;
        }
        $this->persistConfig();
        GenerateBusinessSummaryJob::dispatch($this->session->id);
        $this->summaryLoading = true;
        $this->summary = null;
    }

    public function generateSummary(AssistantChatService $assistant): void
    {
        if (! $this->session)
        {
            return;
        }
        $challenge = trim((string) ($this->config['business_challenge'] ?? ''));
        $hash = $challenge !== '' ? hash('sha256', $challenge) : '';
        $saved = $this->session->fresh()->config ?? [];
        if ($hash !== '' && ($saved['_summary_challenge_hash'] ?? '') === $hash && isset($saved['_summary']) && $saved['_summary'] !== '')
        {
            $this->summary = $saved['_summary'];
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

        $arquetipoContext = $this->buildArquetipoContext();
        if ($arquetipoContext !== '')
        {
            $contextParts[] = '';
            $contextParts[] = 'Arquetipo humano (fecha y hora de nacimiento):';
            $contextParts[] = $arquetipoContext;
        }

        $context = implode("\n", $contextParts);
        $userMessage = $challenge !== ''
            ? "Problemática actual del negocio:\n\n".$challenge."\n\n---\n\n".$context
            : $context;

        try
        {
            $aiStartedAt = now();
            $prompt = Prompt::findByRoutingKey('landing');
            if ($prompt)
            {
                $result = $assistant->run($userMessage, null, null, null, false, 'landing');
                $this->summary = $result['response'] ?? '';
            } else
            {
                $defaultInstruction = 'Eres un consultor de negocio. Con el contexto que te proporcionan (datos del negocio, problemática actual y arquetipo humano por fecha de nacimiento), genera un resumen muy conciso (máximo 1 párrafo corto o 3-5 puntos) de lo que esta empresa necesita para mejorar. Sé directo y práctico.';
                $agent = agent(instructions: $defaultInstruction, messages: [], tools: []);
                $response = $agent->prompt($userMessage, [], AiTasks::provider('summary'));
                $this->summary = $response->text ?? '';
            }
            $aiFinishedAt = now();

            $this->session->refresh();
            $metadata = $this->session->getStepMetadata();
            $metadata['ai_started_at'] = $aiStartedAt->toIso8601String();
            $metadata['ai_finished_at'] = $aiFinishedAt->toIso8601String();
            $metadata['ai_duration_seconds'] = (int) $aiStartedAt->diffInSeconds($aiFinishedAt);
            $metadata['desafio_prompt'] = $challenge;
            BusinessCreationAiLog::create([
                'business_creation_session_id' => $this->session->id,
                'type' => 'summary',
                'request_payload' => $userMessage,
                'response_payload' => $this->summary,
                'metadata' => $metadata,
            ]);
            $current = $this->session->fresh()->config ?? [];
            $current['_summary'] = $this->summary;
            $current['_summary_challenge_hash'] = $hash;
            $this->session->update(['config' => $current]);
        } catch (\Throwable $e)
        {
            Log::error('Landing business summary failed', ['error' => $e->getMessage()]);
            $this->summary = 'Error al generar el resumen. Intenta de nuevo.';
        }

        $this->summaryLoading = false;
    }

    public function loadInsights(): void
    {
        if (! $this->session)
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
        $this->persistConfig();
        $existing = $this->session->fresh()->config ?? [];
        $existing['_insights_requested_at'] = now()->toIso8601String();
        $this->session->update(['config' => $existing]);
        LoadBusinessCreationInsightsJob::dispatch($this->session->id);
        $this->insightsLoading = true;
        $this->insights = [];
        $this->insightsPhase = 'market_data';
    }

    /**
     * Borra el informe de la sesión en BD (solo landing, solo para uso en local).
     * Permite volver a ejecutar "Generar informe".
     */
    public function clearReportFromSession(): void
    {
        if (! $this->session)
        {
            return;
        }
        $existing = $this->session->fresh()->config ?? [];
        unset($existing['_insights'], $existing['_insights_phase'], $existing['_insights_requested_at']);
        $this->session->update(['config' => $existing]);

        $this->insights = [];
        $this->insightsLoading = false;
        $this->insightsPhase = null;
    }

    /**
     * Borra el resumen del desafío de la sesión (solo landing, solo para uso en local).
     * Permite volver a procesar y regenerar el resumen.
     */
    public function clearSummaryFromSession(): void
    {
        if (! $this->session)
        {
            return;
        }
        $existing = $this->session->fresh()->config ?? [];
        unset($existing['_summary'], $existing['_summary_challenge_hash']);
        $this->session->update(['config' => $existing]);

        $this->summary = '';
        $this->summaryLoading = false;
    }

    /**
     * Polled when on step 5: syncs _summary from session (from queue job) into component state.
     */
    public function checkSummaryReady(): void
    {
        if (! $this->session || ($this->step !== 5 && $this->step !== 6))
        {
            return;
        }
        $session = $this->session->fresh();
        $summary = $session->config['_summary'] ?? null;
        if ($summary !== null && $summary !== '')
        {
            $this->summary = (string) $summary;
            $this->summaryLoading = false;
        }
    }

    public function hydrate(): void
    {
        if ($this->step === 6 && $this->insightsLoading && $this->session)
        {
            $this->checkInsightsReady();
        }
        if (($this->step === 5 || $this->step === 6) && $this->summaryLoading && $this->session)
        {
            $this->checkSummaryReady();
        }
        if ($this->step === 6 && $this->finalFlowRequested)
        {
            $this->processFinalFlow();
        }
    }

    /**
     * Polled when on step 6: syncs _insights from session (from queue job) into component state.
     * When using Redis queue the job runs in a worker and writes to the session model; this picks it up.
     */
    public function checkInsightsReady(?BusinessCreationInsightsService $insightsService = null): void
    {
        if (! $this->session || $this->step !== 6)
        {
            return;
        }
        $insightsService ??= app(BusinessCreationInsightsService::class);
        $session = $this->session->fresh();
        $this->insightsPhase = $session->config['_insights_phase'] ?? null;
        $insights = $session->config['_insights'] ?? null;
        if (! empty($insights) && is_array($insights))
        {
            $this->insights = $insights;
            $this->insightsLoading = false;
            $this->insightsPhase = null;

            $existing = $session->config ?? [];
            unset($existing['_insights_requested_at']);
            $session->update(['config' => $existing]);

            return;
        }

        $requestedAt = $session->config['_insights_requested_at'] ?? null;
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
            $this->insights = $insightsService->run($session);
        } catch (\Throwable $e)
        {
            Log::warning('Landing insights fallback failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
            $this->insights = [
                'potential_clients_summary' => 'No se pudo generar el informe ahora. Intenta de nuevo en unos minutos.',
            ];
        }

        $this->insightsLoading = false;
        $this->insightsPhase = null;

        $existing = $session->fresh()->config ?? [];
        $existing['_insights'] = $this->insights;
        unset($existing['_insights_phase'], $existing['_insights_requested_at']);
        $session->update(['config' => $existing]);
    }

    public function submit(): void
    {
        $this->persistConfig();
        $this->finalFlowRequested = true;
        $this->processFinalFlow();
    }

    public function checkProcessingReady(): void
    {
        if ($this->step !== 6)
        {
            return;
        }
        if ($this->summaryLoading)
        {
            $this->checkSummaryReady();
        }
        if ($this->insightsLoading)
        {
            $this->checkInsightsReady();
        }
        if ($this->finalFlowRequested)
        {
            $this->processFinalFlow();
        }
    }

    private function processFinalFlow(): void
    {
        if ($this->insightsLoading || $this->summaryLoading)
        {
            return;
        }

        $needsSummary = filled($this->config['business_challenge'] ?? null) && empty($this->summary ?? null);
        if ($needsSummary)
        {
            $this->finalFlowPhase = 'summary';
            $this->triggerSummaryIfChanged();

            return;
        }

        $hasReport = ! empty($this->insights['potential_clients_summary'] ?? null);
        if (! $hasReport)
        {
            $this->finalFlowPhase = 'insights';
            $this->loadInsights();

            return;
        }

        $email = $this->getReportRecipientEmail();
        if ($email === null)
        {
            $this->showEmailRequired = true;
            $this->finalFlowRequested = false;
            $this->finalFlowPhase = null;

            return;
        }
        $this->finalFlowPhase = 'email';
        $this->sendReportAndFinish($email);
    }

    public function provideEmail(): void
    {
        $this->validate([
            'config.contact_email' => 'required|email',
        ], [
            'config.contact_email.required' => __('Indicá tu email para recibir el informe.'),
            'config.contact_email.email' => __('El email no es válido.'),
        ]);
        $this->persistConfig();
        $email = trim((string) $this->config['contact_email']);
        $this->showEmailRequired = false;
        $this->sendReportAndFinish($email);
    }

    private function getReportRecipientEmail(): ?string
    {
        $personal = trim((string) ($this->config['contact_email'] ?? ''));
        if ($personal !== '')
        {
            return $personal;
        }
        $business = trim((string) ($this->config['business_email'] ?? ''));

        return $business !== '' ? $business : null;
    }

    private function sendReportAndFinish(string $email): void
    {
        $summary = $this->summary ?? ($this->session->fresh()->config['_summary'] ?? null);
        try
        {
            $mailer = Mail::to($email);
            $copyTo = trim((string) config('mail.from.address', ''));
            if ($copyTo !== '' && strcasecmp($copyTo, $email) !== 0)
            {
                $mailer->bcc($copyTo);
            }
            $mailer->send(new BusinessCreationReportMail(
                $this->config,
                $summary,
                $this->insights,
            ));
        } catch (\Throwable $e)
        {
            Log::error('Business creation report email failed', [
                'session_id' => $this->session?->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
        if ($this->session)
        {
            $this->session->update(['completed_at' => now()]);
            $this->reportSent = true;
        }
        $this->finalFlowRequested = false;
        $this->finalFlowPhase = null;
    }

    private function normalizeLocationForSearch(): array
    {
        $city = trim((string) ($this->config['city'] ?? ''));
        $addressLocation = trim((string) ($this->config['business_location'] ?? ''));
        $country = trim((string) ($this->config['country'] ?? ''));

        $locations = array_filter([$city, $addressLocation, $country]);
        if ($locations === [])
        {
            return [];
        }

        return ['organization_locations' => array_values($locations)];
    }

    /** @param  array<string, mixed>  $location */
    private function buildSectorAndLocationFilters(array $location, string $industry): array
    {
        $industry = trim($industry);
        $hasLocation = $location !== [] && ! empty($location['organization_locations'] ?? []);
        $hasSector = $industry !== '';

        if ($hasLocation && $hasSector)
        {
            return array_merge($location, ['q_keywords' => $industry]);
        }
        if ($hasLocation)
        {
            return $location;
        }
        if ($hasSector)
        {
            return ['q_keywords' => $industry];
        }

        return [];
    }

    private function fetchWebsiteContent(string $url): string
    {
        $url = trim($url);
        if ($url === '' || ! Str::isUrl($url))
        {
            return '';
        }
        try
        {
            $response = Http::timeout(12)->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; HumanoBot/1.0)'])->get($url);
            if (! $response->successful())
            {
                return '';
            }
            $html = $response->body();
            $text = strip_tags(preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html));
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = preg_replace('/\s+/u', ' ', $text);

            return Str::limit(trim($text), 4000);
        } catch (\Throwable $e)
        {
            return '';
        }
    }

    private function buildLinksContext(): string
    {
        $links = [];
        $urlKeys = [
            'business_website' => 'Sitio web', 'twitter' => 'X / Twitter', 'facebook' => 'Facebook',
            'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube',
            'tiktok' => 'TikTok', 'whatsapp_url' => 'WhatsApp', 'telegram' => 'Telegram',
            'pinterest' => 'Pinterest', 'threads' => 'Threads',
        ];
        foreach ($urlKeys as $key => $label)
        {
            $val = trim((string) ($this->config[$key] ?? ''));
            if ($val !== '' && (Str::startsWith($val, 'http') || Str::startsWith($val, 'https')))
            {
                $links[] = $label.': '.$val;
            }
        }

        return implode("\n", $links);
    }

    /**
     * @param  array<string, mixed>  $location
     * @param  array<string, int>  $byIndustry
     */
    private function generateMarketReport(
        string $description,
        string $website,
        string $websiteContent,
        string $linksContext,
        string $industry,
        array $location,
        int $businessesNearby,
        int $prospects,
        array $byIndustry,
    ): ?string {
        $locationStr = implode(', ', array_filter($location['organization_locations'] ?? []));
        $industryCount = array_sum($byIndustry);

        $contextParts = [];
        $contextParts[] = '**Datos del negocio**';
        $contextParts[] = '- Nombre / descripción: '.($description !== '' ? $description : '(no indicada)');
        $contextParts[] = '- Sector / rubro: '.($industry !== '' ? $industry : '(no indicado)');
        $contextParts[] = '- Ubicación: '.($locationStr !== '' ? $locationStr : '(no indicada)');
        if ($website !== '')
        {
            $contextParts[] = '- URL del sitio web: '.$website;
        }
        if ($websiteContent !== '')
        {
            $contextParts[] = "\n**Contenido extraído del sitio web:**\n".$websiteContent;
        }
        if ($linksContext !== '')
        {
            $contextParts[] = "\n**Enlaces (redes, etc.):**\n".$linksContext;
        }
        $contextParts[] = "\n**Datos de mercado:**";
        $contextParts[] = '- Negocios en su zona: '.$businessesNearby;
        $contextParts[] = '- Prospectos: '.$prospects;
        if ($byIndustry !== [])
        {
            $contextParts[] = '- Empresas en el sector: '.$industryCount;
        }

        $arquetipoContext = $this->buildArquetipoContext();
        if ($arquetipoContext !== '')
        {
            $contextParts[] = "\n**Arquetipo humano (personalidad según fecha/hora de nacimiento):**";
            $contextParts[] = $arquetipoContext;
        }

        $fullContext = implode("\n", $contextParts);
        if (trim($description) === '' && $websiteContent === '' && $linksContext === '')
        {
            return null;
        }

        $arquetipoInstruction = $arquetipoContext !== ''
            ? ' Ten en cuenta el arquetipo humano indicado para que las recomendaciones sean acordes a su personalidad.'
            : '';

        $instruction = <<<PROMPT
Eres un consultor de negocio y estrategia de mercado. Genera un **informe de mercado** útil y detallado en español, usando TODA la información que te pasan.{$arquetipoInstruction}

Responde en Markdown, con estas secciones (usa **negrita** para los títulos):

1. **Definición del producto/servicio**
2. **Posicionamiento frente a competidores**
3. **Cliente ideal**
4. **Oportunidades y recomendaciones**

Sé específico y práctico. No menciones fuentes de datos ni APIs.
PROMPT;

        try
        {
            $aiStartedAt = now();
            $agent = agent(instructions: $instruction, messages: [], tools: []);
            $response = $agent->prompt($fullContext, [], AiTasks::provider('insight'));
            $aiFinishedAt = now();
            $text = $response->text ? trim($response->text) : null;

            if ($this->session && $text !== null)
            {
                $this->session->refresh();
                $metadata = $this->session->getStepMetadata();
                $metadata['ai_started_at'] = $aiStartedAt->toIso8601String();
                $metadata['ai_finished_at'] = $aiFinishedAt->toIso8601String();
                $metadata['ai_duration_seconds'] = (int) $aiStartedAt->diffInSeconds($aiFinishedAt);
                BusinessCreationAiLog::create([
                    'business_creation_session_id' => $this->session->id,
                    'type' => 'market_report',
                    'request_payload' => $fullContext,
                    'response_payload' => $text,
                    'metadata' => $metadata,
                ]);
            }

            return $text;
        } catch (\Throwable $e)
        {
            Log::warning('Landing market report failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function buildArquetipoContext(): string
    {
        $birthDate = $this->config['birth_date'] ?? null;
        if (! $birthDate)
        {
            return '';
        }
        try
        {
            $astral = new AstralChartService;
            $birthCarbon = Carbon::parse($birthDate);
            $zodiac = $astral->getZodiacSign($birthCarbon);
            $northNode = $astral->getNorthNode($birthCarbon);
            $lines = [
                '- Signo zodiacal: '.($zodiac['sign'] ?? '').' '.($zodiac['symbol'] ?? '').' ('.($zodiac['element'] ?? '').')',
                '- Nodo Norte: '.($northNode['north'] ?? ''),
                '- Nodo Sur: '.($northNode['south'] ?? ''),
            ];
            if (! empty($this->config['birth_time']))
            {
                $lines[] = '- Hora de nacimiento: '.$this->config['birth_time'];
            }

            return implode("\n", $lines);
        } catch (\Throwable $e)
        {
            Log::warning('AstralChartService in landing arquetipo context', ['error' => $e->getMessage()]);

            return '';
        }
    }

    public function render()
    {
        return view('livewire.settings.business-config-wizard');
    }
}
