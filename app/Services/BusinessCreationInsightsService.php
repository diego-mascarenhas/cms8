<?php

namespace App\Services;

use App\Models\BusinessCreationAiLog;
use App\Models\BusinessCreationSession;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\Lab;

use function Laravel\Ai\agent;

class BusinessCreationInsightsService
{
    public function __construct(
        protected ApolloService $apolloService,
        protected AstralChartService $astralChartService,
    ) {}

    /**
     * Generate market insights for a business creation session. Saves result to session config.
     *
     * @return array<string, mixed>
     */
    public function run(BusinessCreationSession $session): array
    {
        @set_time_limit(120);
        $insights = $this->buildInsightsArray(
            $session->config ?? [],
            $session,
            fn (string $phase) => $this->setInsightsPhase($session, $phase),
        );

        $existing = $session->fresh()->config ?? [];
        $existing['_insights'] = $insights;
        unset($existing['_insights_phase']);
        $session->update(['config' => $existing]);

        return $insights;
    }

    /**
     * Same pipeline as {@see run()} but reads/writes team {@see Team::getSetting('business_config')}.
     *
     * @return array<string, mixed>
     */
    public function runForTeam(Team $team): array
    {
        @set_time_limit(120);
        $team->refresh();
        $config = $this->decodeTeamBusinessConfig($team);
        $insights = $this->buildInsightsArray(
            $config,
            null,
            fn (string $phase) => $this->setTeamInsightsPhase($team, $phase),
        );
        $this->finalizeTeamInsights($team, $insights);

        return $insights;
    }

    /**
     * @param  callable(string): void  $setPhase
     * @return array<string, mixed>
     */
    private function buildInsightsArray(array $config, ?BusinessCreationSession $sessionForLog, callable $setPhase): array
    {
        $location = $this->normalizeLocationForSearch($config);
        $industry = trim((string) ($config['business_industry'] ?? ''));
        $description = trim((string) ($config['business_description'] ?? ''));
        $website = trim((string) ($config['business_website'] ?? ''));

        $filtersSectorAndZone = $this->buildSectorAndLocationFilters($location, $industry);

        $aiPhaseTimeline = [];
        $aiPhaseTimeline[] = ['phase' => 'market_data', 'started_at' => now()->toIso8601String()];
        $setPhase('market_data');

        $apolloStartedAt = null;
        $apolloFinishedAt = null;

        try
        {
            $businessesNearby = 0;
            $prospects = 0;
            $byIndustry = [];

            $seniorityCSuite = 0;
            $seniorityDirector = 0;
            $seniorityManager = 0;

            $apolloStartedAt = now();
            if ($filtersSectorAndZone !== [])
            {
                $orgResult = $this->apolloService->searchOrganizations($filtersSectorAndZone, 1, 1);
                $businessesNearby = $orgResult['total_entries'] ?? 0;
                $peopleResult = $this->apolloService->searchPeople($filtersSectorAndZone, 1, 1);
                $prospects = $peopleResult['total_entries'] ?? 0;

                $basePeopleFilters = $filtersSectorAndZone;
                try
                {
                    $cSuiteResult = $this->apolloService->searchPeople(array_merge($basePeopleFilters, ['person_seniorities' => ['c_suite']]), 1, 1);
                    $seniorityCSuite = $cSuiteResult['total_entries'] ?? 0;
                    $dirResult = $this->apolloService->searchPeople(array_merge($basePeopleFilters, ['person_seniorities' => ['director']]), 1, 1);
                    $seniorityDirector = $dirResult['total_entries'] ?? 0;
                    $mgrResult = $this->apolloService->searchPeople(array_merge($basePeopleFilters, ['person_seniorities' => ['manager']]), 1, 1);
                    $seniorityManager = $mgrResult['total_entries'] ?? 0;
                } catch (\Throwable $e)
                {
                    Log::debug('Apollo seniority counts skipped', ['error' => $e->getMessage()]);
                }
            }

            $sectorTotalCount = 0;
            if ($industry !== '')
            {
                $industryOnlyFilters = ['q_keywords' => $industry];
                $industryResult = $this->apolloService->searchOrganizations(array_merge($location, $industryOnlyFilters), 1, 1);
                $byIndustry = [$industry => $industryResult['total_entries'] ?? 0];
                $sectorOnlyResult = $this->apolloService->searchOrganizations($industryOnlyFilters, 1, 1);
                $sectorTotalCount = $sectorOnlyResult['total_entries'] ?? 0;
            }
            if ($apolloStartedAt !== null)
            {
                $apolloFinishedAt = now();
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

            $aiPhaseTimeline[array_key_last($aiPhaseTimeline)]['completed_at'] = now()->toIso8601String();
            $aiPhaseTimeline[] = ['phase' => 'web', 'started_at' => now()->toIso8601String()];
            $setPhase('web');
            $websiteContent = $this->fetchWebsiteContent($website);
            $linksContext = $this->buildLinksContext($config);

            $aiPhaseTimeline[array_key_last($aiPhaseTimeline)]['completed_at'] = now()->toIso8601String();
            $aiPhaseTimeline[] = ['phase' => 'recommendations', 'started_at' => now()->toIso8601String()];
            $setPhase('recommendations');
            $potentialClientsSummary = $this->generateMarketReport(
                $sessionForLog,
                $config,
                $description,
                $website,
                $websiteContent,
                $linksContext,
                $industry,
                $location,
                $businessesNearby,
                $prospects,
                $byIndustry,
                $sectorTotalCount,
                $aiPhaseTimeline,
                $apolloStartedAt,
                $apolloFinishedAt,
            );

            return array_filter([
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
            Log::warning('Business creation insights failed', [
                'error' => $e->getMessage(),
                'session_id' => $sessionForLog?->id,
            ]);
            try
            {
                $websiteContent = $this->fetchWebsiteContent($website);
                $linksContext = $this->buildLinksContext($config);
                $fallbackReport = $this->generateMarketReport($sessionForLog, $config, $description, $website, $websiteContent, $linksContext, $industry, $location, 0, 0, [], 0, [], null, null);

                return ['potential_clients_summary' => $fallbackReport];
            } catch (\Throwable $inner)
            {
                return ['potential_clients_summary' => 'No se pudo generar el informe. Comprueba Rubro, Ubicación o País y vuelve a intentarlo.'];
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeTeamBusinessConfig(Team $team): array
    {
        $existing = $team->getSetting('business_config', []);
        if (is_string($existing))
        {
            return json_decode($existing, true) ?: [];
        }

        return is_array($existing) ? $existing : [];
    }

    private function setTeamInsightsPhase(Team $team, string $phase): void
    {
        $team->refresh();
        $existing = $this->decodeTeamBusinessConfig($team);
        $existing['_insights_phase'] = $phase;
        $team->setSetting('business_config', $existing, [
            'type' => 'json',
            'group' => 'business-config',
        ]);
    }

    /**
     * @param  array<string, mixed>  $insights
     */
    private function finalizeTeamInsights(Team $team, array $insights): void
    {
        $team->refresh();
        $existing = $this->decodeTeamBusinessConfig($team);
        $existing['_insights'] = $insights;
        unset($existing['_insights_phase'], $existing['_insights_requested_at']);
        $team->setSetting('business_config', $existing, [
            'type' => 'json',
            'group' => 'business-config',
        ]);
    }

    private function setInsightsPhase(BusinessCreationSession $session, string $phase): void
    {
        $existing = $session->fresh()->config ?? [];
        $existing['_insights_phase'] = $phase;
        $session->update(['config' => $existing]);
    }

    /**
     * Ubicación para búsqueda: solo ciudad y país (menos restrictivo que incluir dirección).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function normalizeLocationForSearch(array $config): array
    {
        $city = trim((string) ($config['city'] ?? ''));
        $country = trim((string) ($config['country'] ?? ''));

        $locations = array_filter([$city, $country]);
        if ($locations === [])
        {
            return [];
        }

        return ['organization_locations' => array_values($locations)];
    }

    /**
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

    /**
     * @param  array<string, mixed>  $config
     */
    private function buildLinksContext(array $config): string
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
            $val = trim((string) ($config[$key] ?? ''));
            if ($val !== '' && (Str::startsWith($val, 'http') || Str::startsWith($val, 'https')))
            {
                $links[] = $label.': '.$val;
            }
        }

        return implode("\n", $links);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $location
     * @param  array<string, int>  $byIndustry
     */
    private function buildArquetipoContext(array $config): string
    {
        $birthDate = $config['birth_date'] ?? null;
        if (! $birthDate)
        {
            return '';
        }
        try
        {
            $birthCarbon = Carbon::parse($birthDate);
            $zodiac = $this->astralChartService->getZodiacSign($birthCarbon);
            $northNode = $this->astralChartService->getNorthNode($birthCarbon);
            $lines = [
                '- Signo zodiacal: '.($zodiac['sign'] ?? '').' '.($zodiac['symbol'] ?? '').' ('.($zodiac['element'] ?? '').')',
                '- Nodo Norte: '.($northNode['north'] ?? ''),
                '- Nodo Sur: '.($northNode['south'] ?? ''),
            ];
            if (! empty($config['birth_time']))
            {
                $lines[] = '- Hora de nacimiento: '.$config['birth_time'];
            }

            return implode("\n", $lines);
        } catch (\Throwable $e)
        {
            Log::warning('AstralChartService in business creation insights', ['error' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $location
     * @param  array<string, int>  $byIndustry
     * @param  array<int, array{phase: string, started_at: string, completed_at?: string}>  $aiPhaseTimeline
     */
    private function generateMarketReport(
        ?BusinessCreationSession $session,
        array $config,
        string $description,
        string $website,
        string $websiteContent,
        string $linksContext,
        string $industry,
        array $location,
        int $businessesNearby,
        int $prospects,
        array $byIndustry,
        int $sectorTotalCount = 0,
        array $aiPhaseTimeline = [],
        ?Carbon $apolloStartedAt = null,
        ?Carbon $apolloFinishedAt = null,
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
        $contextParts[] = "\n**Datos de mercado** (indicadores obtenidos de bases de datos de empresas y profesionales por sector y ubicación):";
        if ($businessesNearby > 0)
        {
            $contextParts[] = '- Negocios en su zona (sector + ubicación): '.$businessesNearby;
        }
        if ($prospects > 0)
        {
            $contextParts[] = '- Prospectos en su zona: '.$prospects;
        }
        if ($byIndustry !== [] && $industryCount > 0)
        {
            $contextParts[] = '- Empresas en el sector en su zona: '.$industryCount;
        }
        if ($sectorTotalCount > 0)
        {
            $contextParts[] = '- Empresas en el sector (referencia global): '.$sectorTotalCount;
        }
        if ($businessesNearby === 0 && $prospects === 0 && $industryCount === 0 && $sectorTotalCount === 0)
        {
            $contextParts[] = '- No se incluyen cifras en zona (ningún resultado con los filtros usados).';
        }

        $arquetipoContext = $this->buildArquetipoContext($config);
        if ($arquetipoContext !== '')
        {
            $contextParts[] = "\n**Arquetipo humano (personalidad según fecha/hora de nacimiento):**";
            $contextParts[] = $arquetipoContext;
        }

        $challenge = trim((string) ($config['business_challenge'] ?? ''));
        if ($challenge !== '')
        {
            $contextParts[] = "\n**Desafío / problemática actual del negocio:**";
            $contextParts[] = $challenge;
        }
        $summary = trim((string) ($config['_summary'] ?? ''));
        if ($summary !== '')
        {
            $contextParts[] = "\n**Resumen de lo que la empresa necesita para mejorar (generado previamente):**";
            $contextParts[] = $summary;
        }

        $fullContext = implode("\n", $contextParts);
        if (trim($description) === '' && $websiteContent === '' && $linksContext === '')
        {
            return null;
        }

        $arquetipoInstruction = $arquetipoContext !== ''
            ? ' Ten en cuenta el arquetipo humano indicado para que las recomendaciones sean acordes a su personalidad.'
            : '';
        $desafioInstruction = ($challenge !== '' || $summary !== '')
            ? ' Incluye el desafío o problemática del negocio y el resumen de lo que necesitan para mejorar cuando estén indicados; que el informe sea coherente con ellos.'
            : '';

        $instruction = <<<PROMPT
Eres un consultor de negocio y estrategia de mercado. Genera un **informe de mercado** útil y detallado en español, usando TODA la información que te pasan.{$arquetipoInstruction}{$desafioInstruction}

Las cifras "en su zona" dependen del filtro de ubicación; si son bajas o cero pero el sector tiene muchas empresas a nivel global (referencia global), no digas que el mercado está vacío ni que parten de cero: en muchos sectores (p. ej. desarrollo de software) hay gran actividad mundial. Usa la referencia global para matizar el posicionamiento.

Responde en Markdown, con estas secciones (usa **negrita** para los títulos):

1. **Definición del producto/servicio**
2. **Posicionamiento frente a competidores**
3. **Cliente ideal**
4. **Oportunidades y recomendaciones**

Sé específico y práctico. No nombres proveedores ni APIs; puedes aludir de forma genérica a "los datos de mercado", "los indicadores de sector" o "las cifras consultadas".
PROMPT;

        try
        {
            $aiStartedAt = now();
            $agent = agent(instructions: $instruction, messages: [], tools: []);
            $response = $agent->prompt($fullContext, [], Lab::Anthropic);
            $aiFinishedAt = now();
            $text = $response->text ? trim($response->text) : null;

            if ($text !== null && $session !== null)
            {
                $session->refresh();
                $metadata = $session->getStepMetadata();
                if ($aiPhaseTimeline !== [])
                {
                    $aiPhaseTimeline[array_key_last($aiPhaseTimeline)]['completed_at'] = $aiFinishedAt->toIso8601String();
                    $metadata['ai_phase_timeline'] = $aiPhaseTimeline;
                }
                $metadata['ai_started_at'] = $aiStartedAt->toIso8601String();
                $metadata['ai_finished_at'] = $aiFinishedAt->toIso8601String();
                $metadata['ai_duration_seconds'] = (int) $aiStartedAt->diffInSeconds($aiFinishedAt);
                if ($apolloStartedAt !== null && $apolloFinishedAt !== null)
                {
                    $metadata['apollo_started_at'] = $apolloStartedAt->toIso8601String();
                    $metadata['apollo_finished_at'] = $apolloFinishedAt->toIso8601String();
                    $metadata['apollo_duration_seconds'] = (int) $apolloStartedAt->diffInSeconds($apolloFinishedAt);
                }
                BusinessCreationAiLog::create([
                    'business_creation_session_id' => $session->id,
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
}
