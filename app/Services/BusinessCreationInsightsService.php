<?php

namespace App\Services;

use App\Models\BusinessCreationAiLog;
use App\Models\BusinessCreationSession;
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
        $config = $session->config ?? [];
        $location = $this->normalizeLocationForSearch($config);
        $industry = trim((string) ($config['business_industry'] ?? ''));
        $description = trim((string) ($config['business_description'] ?? ''));
        $website = trim((string) ($config['business_website'] ?? ''));

        $filtersSectorAndZone = $this->buildSectorAndLocationFilters($location, $industry);

        try
        {
            $businessesNearby = 0;
            $prospects = 0;
            $byIndustry = [];

            if ($filtersSectorAndZone !== [])
            {
                $orgResult = $this->apolloService->searchOrganizations($filtersSectorAndZone, 1, 1);
                $businessesNearby = $orgResult['total_entries'] ?? 0;
                $peopleResult = $this->apolloService->searchPeople($filtersSectorAndZone, 1, 1);
                $prospects = $peopleResult['total_entries'] ?? 0;
            }

            if ($industry !== '')
            {
                $industryOnlyFilters = ['q_keywords' => $industry];
                $industryResult = $this->apolloService->searchOrganizations(array_merge($location, $industryOnlyFilters), 1, 1);
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

            $websiteContent = $this->fetchWebsiteContent($website);
            $linksContext = $this->buildLinksContext($config);
            $potentialClientsSummary = $this->generateMarketReport(
                $session,
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
            );

            $insights = array_filter([
                'businesses_nearby' => $businessesNearby > 0 ? $businessesNearby : null,
                'prospects' => $prospects > 0 ? $prospects : null,
                'by_industry' => $byIndustry ?: null,
                'chart_series' => ($chartSeries['categories'] && array_sum($chartSeries['series']) > 0) ? $chartSeries : null,
                'potential_clients_summary' => $potentialClientsSummary,
            ]);
        } catch (\Throwable $e)
        {
            Log::warning('Business creation insights failed', ['error' => $e->getMessage(), 'session_id' => $session->id]);
            try
            {
                $websiteContent = $this->fetchWebsiteContent($website);
                $linksContext = $this->buildLinksContext($config);
                $fallbackReport = $this->generateMarketReport($session, $config, $description, $website, $websiteContent, $linksContext, $industry, $location, 0, 0, []);
                $insights = ['potential_clients_summary' => $fallbackReport];
            } catch (\Throwable $inner)
            {
                $insights = ['potential_clients_summary' => 'No se pudo generar el informe. Comprueba Rubro, Ubicación o País y vuelve a intentarlo.'];
            }
        }

        $existing = $session->fresh()->config ?? [];
        $existing['_insights'] = $insights;
        $session->update(['config' => $existing]);

        return $insights;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function normalizeLocationForSearch(array $config): array
    {
        $city = trim((string) ($config['city'] ?? ''));
        $addressLocation = trim((string) ($config['business_location'] ?? ''));
        $country = trim((string) ($config['country'] ?? ''));

        $locations = array_filter([$city, $addressLocation, $country]);
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
     */
    private function generateMarketReport(
        BusinessCreationSession $session,
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

        $arquetipoContext = $this->buildArquetipoContext($config);
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
            $agent = agent(instructions: $instruction, messages: [], tools: []);
            $response = $agent->prompt($fullContext, [], Lab::Anthropic);
            $text = $response->text ? trim($response->text) : null;

            if ($text !== null)
            {
                BusinessCreationAiLog::create([
                    'business_creation_session_id' => $session->id,
                    'type' => 'market_report',
                    'request_payload' => $fullContext,
                    'response_payload' => $text,
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
