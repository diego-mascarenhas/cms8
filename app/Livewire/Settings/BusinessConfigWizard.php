<?php

namespace App\Livewire\Settings;

use App\Models\Prompt;
use App\Models\Team;
use App\Services\ApolloService;
use App\Services\AssistantChatService;
use App\Services\AstralChartService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

    /** Last problemática text we sent to AI; skip reprocessing if unchanged. */
    public ?string $lastProcessedProblematica = null;

    /** @var array<string, mixed> */
    public array $insights = [];

    public bool $insightsLoading = false;

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
        'business_tagline', 'business_description', 'business_problematica',
        'first_name', 'last_name', 'birth_date', 'birth_time', 'country', 'language',
        'address', 'landmark', 'pincode', 'city',
        'twitter', 'facebook', 'instagram', 'linkedin', 'youtube', 'tiktok',
        'whatsapp_url', 'telegram', 'pinterest', 'threads',
        'wants_profundizar',
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
        $payload = [];
        foreach (self::$configKeys as $key)
        {
            $value = $this->config[$key] ?? null;
            if ($value !== null && $value !== '' && $value !== [])
            {
                $payload[$key] = is_array($value) ? $value : (string) $value;
            }
        }
        $this->team->setSetting('business_config', $payload, [
            'type' => 'json',
            'group' => 'business-config',
        ]);
    }

    public function setWantsProfundizar(string $value): void
    {
        if ($value !== 'si' && $value !== 'no')
        {
            return;
        }
        $this->config['wants_profundizar'] = $value;
        $this->persistConfig();
    }

    public function submit(): void
    {
        $this->persistConfig();
        $this->dispatch('saved');
    }

    public function triggerSummaryIfChanged(AssistantChatService $assistant): void
    {
        if (trim((string) ($this->config['business_problematica'] ?? '')) === '')
        {
            return;
        }
        $this->generateSummary($assistant);
    }

    public function generateSummary(AssistantChatService $assistant): void
    {
        $problematica = trim((string) ($this->config['business_problematica'] ?? ''));
        if ($problematica !== '' && $problematica === $this->lastProcessedProblematica && $this->summary !== null)
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
        $userMessage = $problematica !== ''
            ? "Problemática actual del negocio:\n\n".$problematica."\n\n---\n\n".$context
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

        $this->lastProcessedProblematica = $problematica;
        $this->summaryLoading = false;
    }

    /**
     * Load market insights: businesses nearby, prospects, by industry, and AI suggestion for potential clients.
     * Uses external data sources (no provider names shown to the user).
     */
    public function loadInsights(ApolloService $apolloService): void
    {
        $industry = trim((string) ($this->config['business_industry'] ?? ''));
        $description = trim((string) ($this->config['business_description'] ?? ''));
        $tagline = trim((string) ($this->config['business_tagline'] ?? ''));
        if ($industry === '' || $description === '' || $tagline === '')
        {
            return;
        }
        @set_time_limit(120);
        $this->insightsLoading = true;
        $this->insights = [];

        $location = $this->normalizeLocationForSearch();
        $industry = trim((string) ($this->config['business_industry'] ?? ''));
        $description = trim((string) ($this->config['business_description'] ?? ''));
        $website = trim((string) ($this->config['business_website'] ?? ''));

        $filtersSectorAndZone = $this->buildSectorAndLocationFilters($location, $industry);

        try
        {
            $businessesNearby = 0;
            $prospects = 0;
            $byIndustry = [];

            $seniorityCSuite = 0;
            $seniorityDirector = 0;
            $seniorityManager = 0;

            if ($filtersSectorAndZone !== [])
            {
                $orgResult = $apolloService->searchOrganizations($filtersSectorAndZone, 1, 1);
                $businessesNearby = $orgResult['total_entries'] ?? 0;

                $peopleResult = $apolloService->searchPeople($filtersSectorAndZone, 1, 1);
                $prospects = $peopleResult['total_entries'] ?? 0;

                try
                {
                    $baseFilters = $filtersSectorAndZone;
                    $cSuiteResult = $apolloService->searchPeople(array_merge($baseFilters, ['person_seniorities' => ['c_suite']]), 1, 1);
                    $seniorityCSuite = $cSuiteResult['total_entries'] ?? 0;
                    $dirResult = $apolloService->searchPeople(array_merge($baseFilters, ['person_seniorities' => ['director']]), 1, 1);
                    $seniorityDirector = $dirResult['total_entries'] ?? 0;
                    $mgrResult = $apolloService->searchPeople(array_merge($baseFilters, ['person_seniorities' => ['manager']]), 1, 1);
                    $seniorityManager = $mgrResult['total_entries'] ?? 0;
                } catch (\Throwable $e)
                {
                    Log::debug('Apollo seniority counts skipped', ['error' => $e->getMessage()]);
                }
            }

            if ($industry !== '')
            {
                $industryOnlyFilters = ['q_keywords' => $industry];
                $industryResult = $apolloService->searchOrganizations(
                    array_merge($location, $industryOnlyFilters),
                    1,
                    1,
                );
                $byIndustry = [$industry => $industryResult['total_entries'] ?? 0];
            }

            $chartSeries = [
                'categories' => array_keys($byIndustry) ?: ['Tu sector'],
                'series' => array_values($byIndustry) ?: [0],
            ];
            if ($businessesNearby > 0 && count($chartSeries['categories']) === 1 && $chartSeries['series'][0] === 0)
            {
                $chartSeries['categories'] = ['En tu zona', 'Prospectos'];
                $chartSeries['series'] = [$businessesNearby, $prospects];
            } elseif ($businessesNearby > 0 || $prospects > 0)
            {
                $chartSeries['categories'] = array_merge(['Negocios en tu zona', 'Prospectos'], $chartSeries['categories']);
                $chartSeries['series'] = array_merge([$businessesNearby, $prospects], $chartSeries['series']);
            }
            $nonZeroCount = $chartSeries['series'] ? count(array_filter($chartSeries['series'], fn ($v) => (int) $v > 0)) : 0;
            if ($nonZeroCount < 1)
            {
                $chartSeries = null;
            }

            $websiteContent = $this->fetchWebsiteContent($website);
            $linksContext = $this->buildLinksContext();
            $potentialClientsSummary = $this->generateMarketReport(
                $description,
                $website,
                $websiteContent,
                $linksContext,
                $industry,
                $location,
                $businessesNearby,
                $prospects,
                $byIndustry,
            );

            $this->insights = array_filter([
                'businesses_nearby' => $businessesNearby > 0 ? $businessesNearby : null,
                'prospects' => $prospects > 0 ? $prospects : null,
                'seniority_c_suite' => $seniorityCSuite > 0 ? $seniorityCSuite : null,
                'seniority_director' => $seniorityDirector > 0 ? $seniorityDirector : null,
                'seniority_manager' => $seniorityManager > 0 ? $seniorityManager : null,
                'by_industry' => $byIndustry ?: null,
                'chart_series' => $chartSeries,
                'potential_clients_summary' => $potentialClientsSummary,
            ]);
        } catch (\Throwable $e)
        {
            Log::warning('Business insights load failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            try
            {
                $websiteContent = $this->fetchWebsiteContent($website);
                $linksContext = $this->buildLinksContext();
                $fallbackReport = $this->generateMarketReport(
                    $description,
                    $website,
                    $websiteContent,
                    $linksContext,
                    $industry,
                    $location,
                    0,
                    0,
                    [],
                );
                $this->insights = ['potential_clients_summary' => $fallbackReport];
            } catch (\Throwable $inner)
            {
                Log::warning('Business insights fallback failed', ['error' => $inner->getMessage()]);
                $this->insights = [
                    'potential_clients_summary' => 'No se pudo generar el informe. Comprueba que Rubro, Ubicación o País estén rellenados y vuelve a intentarlo.',
                ];
            }
        }

        $this->insightsLoading = false;
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * Build filters that combine sector (rubro) and location so counts are "in this industry in this zone".
     * E.g. "Tecnología" + "Asturias, España" → organizations/people in Technology in Asturias, Spain.
     *
     * @param  array<string, mixed>  $location
     * @return array<string, mixed>
     */
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
            $response = Http::timeout(12)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; HumanoBot/1.0)',
            ])->get($url);
            if (! $response->successful())
            {
                return '';
            }
            $html = $response->body();
            $text = strip_tags(preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html));
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = preg_replace('/\s+/u', ' ', $text);
            $text = trim($text);

            return Str::limit($text, 4000);
        } catch (\Throwable $e)
        {
            Log::debug('Website content fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * Build a text list of links the user provided (website + social) for context.
     */
    private function buildLinksContext(): string
    {
        $links = [];
        $urlKeys = [
            'business_website' => 'Sitio web',
            'twitter' => 'X / Twitter',
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'linkedin' => 'LinkedIn',
            'youtube' => 'YouTube',
            'tiktok' => 'TikTok',
            'whatsapp_url' => 'WhatsApp',
            'telegram' => 'Telegram',
            'pinterest' => 'Pinterest',
            'threads' => 'Threads',
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
     * Generate a full market report using extracted web content, links, and market data.
     * Includes product definition, competitor positioning, ideal client, and opportunities.
     *
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
        $contextParts[] = '- Nombre / descripción que dio el usuario: '.($description !== '' ? $description : '(no indicada)');
        $contextParts[] = '- Sector / rubro: '.($industry !== '' ? $industry : '(no indicado)');
        $contextParts[] = '- Ubicación: '.($locationStr !== '' ? $locationStr : '(no indicada)');
        if ($website !== '')
        {
            $contextParts[] = '- URL del sitio web: '.$website;
        }
        if ($websiteContent !== '')
        {
            $contextParts[] = "\n**Contenido extraído del sitio web (usa esto para definir el producto y el posicionamiento):**\n".$websiteContent;
        }
        if ($linksContext !== '')
        {
            $contextParts[] = "\n**Enlaces que el usuario ha compartido (redes, etc.):**\n".$linksContext;
        }
        $contextParts[] = "\n**Datos de mercado (para contexto):**";
        $contextParts[] = '- Negocios en su zona: '.$businessesNearby;
        $contextParts[] = '- Prospectos (contactos) en la zona: '.$prospects;
        if ($byIndustry !== [])
        {
            $contextParts[] = '- Empresas en el sector indicado: '.$industryCount;
        }

        $fullContext = implode("\n", $contextParts);
        if (trim($description) === '' && $websiteContent === '' && $linksContext === '')
        {
            return null;
        }

        $instruction = <<<'PROMPT'
Eres un consultor de negocio y estrategia de mercado. Genera un **informe de mercado** útil y detallado en español, usando TODA la información que te pasan: descripción del negocio, contenido extraído de la web del usuario y los enlaces que ha compartido.

Responde en Markdown, con estas secciones (usa **negrita** para los títulos de sección):

1. **Definición del producto/servicio**  
   A partir del contenido extraído del sitio web y de la descripción, define de forma clara qué ofrece este negocio, a quién y con qué valor. Si hay contenido de la web, úsalo como base.

2. **Posicionamiento frente a competidores**  
   Cómo está situado este negocio respecto a su competencia: ventajas diferenciales, nicho, fortalezas y posibles debilidades. Usa el contenido de la web y los canales (enlaces) para inferir su presencia y posicionamiento.

3. **Cliente ideal**  
   A quién le vendría bien este producto o servicio: perfil, tipo de empresa o persona, necesidades que cubre.

4. **Oportunidades y recomendaciones**  
   En función de los datos de mercado (negocios en la zona, prospectos, sector), sugiere 2-4 acciones concretas o oportunidades (ej. segmentos a atacar, canales a potenciar, mensaje a reforzar).

Sé específico y práctico. No menciones fuentes de datos, APIs ni herramientas. Escribe como si el informe saliera de tu análisis como consultor.
PROMPT;

        try
        {
            $agent = agent(
                instructions: $instruction,
                messages: [],
                tools: [],
            );
            $response = $agent->prompt($fullContext, [], Lab::Anthropic);

            return $response->text ? trim($response->text) : null;
        } catch (\Throwable $e)
        {
            Log::warning('Market report generation failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function render()
    {
        return view('livewire.settings.business-config-wizard');
    }
}
