<?php

namespace App\Services\Marketing;

use App\Models\Contact;
use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\Team;
use App\Services\Finance\FinanceCfoBriefService;
use App\Services\Finance\InvoiceAnalyticsService;
use App\Support\AiTasks;
use App\Support\RevisionAlphaOrganization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Laravel\Ai\agent;

class CmoBriefService
{
    public function __construct(
        private readonly InvoiceAnalyticsService $invoiceAnalyticsService,
    ) {}

    /**
     * @return list<array{key: string, title: string, hint: string, fields?: array<string, string>}>
     */
    public static function blocks(): array
    {
        return [
            ['key' => 'situation', 'title' => 'cmo_situation', 'hint' => 'cmo_situation_hint'],
            ['key' => 'dafo', 'title' => 'cmo_dafo', 'hint' => 'cmo_dafo_hint', 'fields' => [
                'fortalezas' => 'cfo_analysis_strengths',
                'debilidades' => 'cfo_analysis_weaknesses',
                'oportunidades' => 'cfo_analysis_opportunities',
                'amenazas' => 'cfo_analysis_threats',
            ]],
            ['key' => 'came', 'title' => 'cmo_came', 'hint' => 'cmo_came_hint', 'fields' => [
                'corregir' => 'cmo_came_correct',
                'afrontar' => 'cmo_came_face',
                'mantener' => 'cmo_came_keep',
                'explotar' => 'cmo_came_exploit',
            ]],
            ['key' => 'eisenhower', 'title' => 'cmo_eisenhower', 'hint' => 'cmo_eisenhower_hint', 'fields' => [
                'c1' => 'cmo_eisenhower_c1',
                'c2' => 'cmo_eisenhower_c2',
                'c3' => 'cmo_eisenhower_c3',
                'c4' => 'cmo_eisenhower_c4',
            ]],
            ['key' => 'empathy', 'title' => 'cmo_empathy', 'hint' => 'cmo_empathy_hint', 'fields' => [
                'thinks' => 'cmo_empathy_thinks',
                'hears' => 'cmo_empathy_hears',
                'sees' => 'cmo_empathy_sees',
                'says' => 'cmo_empathy_says',
                'pains' => 'cmo_empathy_pains',
                'gains' => 'cmo_empathy_gains',
            ]],
            ['key' => 'value_proposition', 'title' => 'cmo_value', 'hint' => 'cmo_value_hint'],
            ['key' => 'business_model', 'title' => 'cmo_bmc', 'hint' => 'cmo_bmc_hint'],
            ['key' => 'lean_canvas', 'title' => 'cmo_lean', 'hint' => 'cmo_lean_hint'],
            ['key' => 'interviews', 'title' => 'cmo_interviews', 'hint' => 'cmo_interviews_hint'],
            ['key' => 'interview_summary', 'title' => 'cmo_interview_summary', 'hint' => 'cmo_interview_summary_hint'],
            ['key' => 'icp', 'title' => 'cmo_icp', 'hint' => 'cmo_icp_hint'],
            ['key' => 'buyer_persona', 'title' => 'cmo_persona', 'hint' => 'cmo_persona_hint'],
            ['key' => 'lookalike', 'title' => 'cmo_lookalike', 'hint' => 'cmo_lookalike_hint'],
            ['key' => 'ansoff', 'title' => 'cmo_ansoff', 'hint' => 'cmo_ansoff_hint', 'fields' => [
                'penetration' => 'cmo_ansoff_penetration',
                'product' => 'cmo_ansoff_product',
                'market' => 'cmo_ansoff_market',
                'diversification' => 'cmo_ansoff_diversification',
            ]],
            ['key' => 'pest', 'title' => 'cmo_pest', 'hint' => 'cmo_pest_hint'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function storedAnalysis(Team $team, int $year): ?array
    {
        $stored = $team->getSetting($this->settingKey($year));

        if (! is_array($stored))
        {
            return null;
        }

        $analysis = $this->normalize($stored);

        if (! $this->hasContent($analysis) && $analysis['brief'] === '')
        {
            return null;
        }

        return $analysis;
    }

    public function remember(Team $team, int $year, bool $refresh = false): string
    {
        if (! $refresh)
        {
            $stored = $this->storedAnalysis($team, $year);

            if ($stored !== null)
            {
                return $stored['brief'];
            }
        }

        $text = $this->suggest($team, $year);
        $failed = __('The CMO suggestion could not be generated.');

        if ($text === $failed)
        {
            return $this->storedAnalysis($team, $year)['brief'] ?? $text;
        }

        $parsed = $this->parse($text);
        $analysis = $parsed ?? $this->normalize(['brief' => $text]);
        $analysis['brief'] = $parsed !== null ? $this->readable($analysis) : $text;
        $analysis['generated_at'] = now()->toIso8601String();

        $team->setSetting($this->settingKey($year), $analysis, [
            'type' => 'json',
            'group' => 'marketing',
        ]);

        return $analysis['brief'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function present(string $text): ?array
    {
        return $this->parse($text);
    }

    public function suggest(Team $team, int $year): string
    {
        $context = $this->context($team, $year);
        $instructions = <<<'TXT'
Eres el CMO de la empresa. Responde solo con JSON válido, en español, sin markdown.
El método es el de marketing comercial digital B2B de Adquiria (https://adquiria.net/): situación inicial, DAFO, CAME, Eisenhower, mapa de empatía, propuesta de valor, Business Model Canvas, Lean Canvas, entrevistas cliente-problema-solución, resumen de entrevistas, perfil de cliente ideal, buyer persona, audiencias similares, Ansoff y PEST.
Usa únicamente los números y los nombres del contexto. No inventes importes, clientes, cargos, países, porcentajes, entrevistas, sentimientos ni un presupuesto de marketing. Si un dato no está, dilo en esa frase.
No hables de correo, WhatsApp, tickets ni de la lista de 60.
El capital social y el colchón de meses son hitos: no propongas gasto nuevo de publicidad como si el colchón ya existiera.
Si not_loaded.customer_interviews es 0, entrevistas, resumen y mapa de empatía dicen que no hay entrevistas cargadas. No describas lo que un cliente piensa, oye, ve o siente.
Si not_loaded.macro_environment es false, PEST dice que el entorno macro no está cargado. No cites tipos de interés, leyes ni desempleo de memoria.
Si not_loaded.product_margin es false, no des un margen por producto. El margen del contexto es el de la empresa.
Si not_loaded.client_employee_count y client_company_revenue son false, el perfil de cliente ideal no inventa empleados ni facturación del cliente. Usa país, localidad, repetición de compra e importe facturado.
google_analytics null: no hables de visitas. publications con posts_last_90_days 0: di que no hay publicaciones en la agenda.
El JSON tiene esta forma, y cada valor es un texto:
{"situation":"","pest":"","dafo":{"fortalezas":"","debilidades":"","oportunidades":"","amenazas":""},"came":{"corregir":"","afrontar":"","mantener":"","explotar":""},"eisenhower":{"c1":"","c2":"","c3":"","c4":""},"empathy":{"thinks":"","hears":"","sees":"","says":"","pains":"","gains":""},"value_proposition":"","business_model":"","lean_canvas":"","interviews":"","interview_summary":"","icp":"","buyer_persona":"","lookalike":"","ansoff":{"penetration":"","product":"","market":"","diversification":""}}
situation resume productos por peso, margen de la empresa y cada cuánto compra el cliente.
came: corregir debilidades, afrontar amenazas, mantener fortalezas, explotar oportunidades. Cada frase nombra la cifra que la justifica.
eisenhower: c1 urgente e importante, c2 importante y no urgente, c3 urgente y no importante, c4 ni urgente ni importante. Solo tareas que salen del contexto (llamados, publicaciones, conversión, productos).
lookalike agrupa la audiencia ya cliente por país y por compra repetida. No inventes un cluster.
ansoff: penetración, desarrollo de producto, desarrollo de mercado, diversificación. Si no hay un segundo mercado o producto en el contexto, la diversificación dice que no hay evidencia.
TXT;

        $userMessage = "CONTEXTO COMERCIAL\n\n".json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        try
        {
            $agent = agent(
                instructions: $instructions,
                messages: [],
                tools: [],
            );
            $response = $agent->prompt(
                $userMessage,
                [],
                AiTasks::provider('assistant'),
                AiTasks::model('assistant'),
                90,
            );
            $text = trim((string) ($response->text ?? ''));
        } catch (Throwable $e)
        {
            Log::error('CmoBriefService::suggest failed', ['error' => $e->getMessage()]);

            return __('The CMO suggestion could not be generated.');
        }

        if ($text === '')
        {
            return __('The CMO suggestion could not be generated.');
        }

        return $text;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(Team $team, int $year): array
    {
        $report = $this->invoiceAnalyticsService->buildYearReport($team->id, $year);
        $currency = (string) $report['reporting_currency'];
        $purchase = $this->purchasePattern($team, $year, $currency);
        $entered = Contact::withoutGlobalScope('team')
            ->withoutGlobalScope('ownership')
            ->where('team_id', $team->id)
            ->whereYear('created_at', $year);

        return [
            'year' => $report['year'],
            'currency' => $currency,
            'method' => 'Adquiria',
            'products' => array_map(fn (array $category): array => [
                'name' => $category['name'],
                'amount' => round((float) $category['total'], 2),
                'share_percent' => round((float) $category['share_percent'], 1),
            ], array_slice($report['income_categories'], 0, 8)),
            'company' => [
                'income' => round((float) $report['summary']['income'], 2),
                'expense' => round((float) $report['summary']['expense'], 2),
                'profit' => round((float) $report['summary']['profit'], 2),
                'margin_percent' => round((float) $report['summary']['margin_percent'], 1),
                'prior_year_income' => round((float) $report['summary']['prior_year_income'], 2),
            ],
            'purchase' => $purchase['totals'],
            'clients_by_country' => $this->clientsByCountry($team),
            'top_clients' => $purchase['top_clients'],
            'interlocutors' => $this->interlocutors($team),
            'leads_entered_this_year' => (clone $entered)->count(),
            'leads_converted_to_client_this_year' => (clone $entered)->where('status_id', 5)->count(),
            'open_leads' => Contact::withoutGlobalScope('team')
                ->withoutGlobalScope('ownership')
                ->where('team_id', $team->id)
                ->whereIn('status_id', [1, 2])
                ->count(),
            'clients' => Contact::withoutGlobalScope('team')
                ->withoutGlobalScope('ownership')
                ->where('team_id', $team->id)
                ->where('status_id', 5)
                ->count(),
            'publications' => app(FinanceCfoBriefService::class)->publications($team),
            'google_analytics' => app(FinanceCfoBriefService::class)->googleAnalytics($team),
            'commercial_work' => $this->commercialWork($team),
            'constraints' => [
                'cushion_months' => (int) config('organization.subsistence.cushion_months', 6),
                'share_capital' => (float) config('organization.subsistence.share_capital_eur', 3000),
                'share_capital_minimum' => (float) config('organization.subsistence.share_capital_minimum_eur', 3000),
            ],
            'customer_interviews' => 0,
            'not_loaded' => [
                'customer_interviews' => 0,
                'client_employee_count' => false,
                'client_company_revenue' => false,
                'macro_environment' => false,
                'product_margin' => false,
                'marketing_budget' => false,
            ],
        ];
    }

    private function settingKey(int $year): string
    {
        return 'marketing_cmo_brief_'.$year;
    }

    /**
     * @return array{totals: array{invoices: int, repeat_clients: int, single_clients: int, amount: float, unconverted_invoices: int}, top_clients: list<array{name: string, country: string, locality: string, invoices: int, amount: float}>}
     */
    private function purchasePattern(Team $team, int $year, string $currency): array
    {
        $rows = Invoice::query()
            ->withoutGlobalScope('team')
            ->where('invoices.team_id', $team->id)
            ->where('invoices.operation', 'sell')
            ->whereYear('invoices.date', $year)
            ->whereNotIn('invoices.status', InvoiceAnalyticsService::EXCLUDED_INVOICE_STATUSES)
            ->whereNotNull('invoices.enterprise_id')
            ->groupBy('invoices.enterprise_id', 'invoices.currency_id')
            ->selectRaw('invoices.enterprise_id as enterprise_id, invoices.currency_id as currency_id, COUNT(*) as invoices, COALESCE(SUM(invoices.total_amount), 0) as amount')
            ->get();

        $codes = Currency::query()
            ->whereIn('id', $rows->pluck('currency_id')->filter()->unique()->all())
            ->pluck('code', 'id');

        $byEnterprise = [];
        $invoices = 0;
        $amount = 0.0;
        $unconverted = 0;

        foreach ($rows as $row)
        {
            $count = (int) $row->invoices;
            $invoices += $count;
            $enterpriseId = (int) $row->enterprise_id;
            $byEnterprise[$enterpriseId]['invoices'] = ($byEnterprise[$enterpriseId]['invoices'] ?? 0) + $count;
            $byEnterprise[$enterpriseId]['amount'] = $byEnterprise[$enterpriseId]['amount'] ?? 0.0;

            $code = strtoupper(trim((string) ($codes[$row->currency_id] ?? '')));
            $raw = (float) $row->amount;

            if ($code === '' || ($code !== $currency && ! $this->canConvert($raw, $code, $currency)))
            {
                $unconverted += $count;

                continue;
            }

            $converted = $code === $currency
                ? $raw
                : (float) ExchangeRate::convert($raw, $code, $currency);
            $byEnterprise[$enterpriseId]['amount'] += $converted;
            $amount += $converted;
        }

        $repeat = 0;
        $single = 0;

        foreach ($byEnterprise as $bucket)
        {
            if ($bucket['invoices'] >= 2)
            {
                $repeat++;
            } elseif ($bucket['invoices'] === 1)
            {
                $single++;
            }
        }

        uasort($byEnterprise, function (array $left, array $right): int
        {
            return [$right['invoices'], $right['amount']] <=> [$left['invoices'], $left['amount']];
        });

        $topIds = array_slice(array_keys($byEnterprise), 0, 8);
        $enterprises = Enterprise::withoutGlobalScope('team')
            ->whereIn('id', $topIds)
            ->get(['id', 'name', 'country', 'locality'])
            ->keyBy('id');

        $top = [];

        foreach ($topIds as $id)
        {
            $enterprise = $enterprises->get($id);
            $bucket = $byEnterprise[$id];
            $top[] = [
                'name' => (string) ($enterprise->name ?? ''),
                'country' => (string) ($enterprise->country ?? ''),
                'locality' => (string) ($enterprise->locality ?? ''),
                'invoices' => (int) $bucket['invoices'],
                'amount' => round((float) $bucket['amount'], 2),
            ];
        }

        return [
            'totals' => [
                'invoices' => $invoices,
                'repeat_clients' => $repeat,
                'single_clients' => $single,
                'amount' => round($amount, 2),
                'unconverted_invoices' => $unconverted,
            ],
            'top_clients' => $top,
        ];
    }

    private function canConvert(float $amount, string $from, string $to): bool
    {
        $converted = ExchangeRate::convert($amount, $from, $to);

        if ($converted === null)
        {
            return false;
        }

        return ! ($converted == 0.0 && $amount != 0.0);
    }

    /**
     * @return list<array{country: string, enterprises: int}>
     */
    private function clientsByCountry(Team $team): array
    {
        return Enterprise::withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->where('type_id', 1)
            ->selectRaw("coalesce(nullif(trim(country), ''), '') as country, COUNT(*) as enterprises")
            ->groupByRaw("coalesce(nullif(trim(country), ''), '')")
            ->orderByDesc('enterprises')
            ->limit(12)
            ->get()
            ->map(fn ($row): array => [
                'country' => (string) $row->country,
                'enterprises' => (int) $row->enterprises,
            ])
            ->all();
    }

    /**
     * @return list<array{position: string, people: int}>
     */
    private function interlocutors(Team $team): array
    {
        return DB::table('contact_enterprise as ce')
            ->join('contacts as c', 'c.id', '=', 'ce.contact_id')
            ->where('c.team_id', $team->id)
            ->whereNull('c.deleted_at')
            ->where('c.status_id', 5)
            ->selectRaw("coalesce(nullif(trim(ce.position), ''), '') as position, COUNT(*) as people")
            ->groupByRaw("coalesce(nullif(trim(ce.position), ''), '')")
            ->orderByDesc('people')
            ->limit(12)
            ->get()
            ->map(fn ($row): array => [
                'position' => (string) $row->position,
                'people' => (int) $row->people,
            ])
            ->all();
    }

    /**
     * @return array{day_call_minimum: int, week_call_minimum: int, call_window: string, call_owner: string, tasks: list<array{lane: string, name: string, person: string, hours: float}>}|null
     */
    private function commercialWork(Team $team): ?array
    {
        if (! RevisionAlphaOrganization::appliesTo($team->id))
        {
            return null;
        }

        $catalog = RevisionAlphaOrganization::subsistenceCatalog();
        $tasks = [];

        foreach (['calls', 'marketing', 'conversion'] as $lane)
        {
            foreach ($catalog['tasks'][$lane] ?? [] as $task)
            {
                $tasks[] = [
                    'lane' => $lane,
                    'name' => (string) ($task['name'] ?? ''),
                    'person' => (string) ($task['person'] ?? ''),
                    'hours' => round((float) ($task['hours'] ?? 0), 1),
                ];
            }
        }

        $start = (string) ($catalog['call_start'] ?? '');
        $end = (string) ($catalog['call_end'] ?? '');

        return [
            'day_call_minimum' => (int) ($catalog['day_minimum'] ?? 0),
            'week_call_minimum' => (int) ($catalog['week_minimum'] ?? 0),
            'call_window' => trim($start.'-'.$end, '-'),
            'call_owner' => (string) ($catalog['call_owner'] ?? ''),
            'tasks' => $tasks,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parse(string $text): ?array
    {
        $clean = trim($text);
        $clean = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $clean) ?? $clean;
        $decoded = json_decode($clean, true);

        if (! is_array($decoded) && preg_match('/\{.*\}/s', $clean, $match) === 1)
        {
            $decoded = json_decode($match[0], true);
        }

        if (! is_array($decoded))
        {
            return null;
        }

        return $this->normalize($decoded);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalize(array $payload): array
    {
        $analysis = [
            'brief' => $this->plainText($payload['brief'] ?? ''),
            'generated_at' => $this->plainText($payload['generated_at'] ?? ''),
        ];

        foreach (self::blocks() as $block)
        {
            if (! isset($block['fields']))
            {
                $analysis[$block['key']] = $this->plainText($payload[$block['key']] ?? '');

                continue;
            }

            $source = is_array($payload[$block['key']] ?? null) ? $payload[$block['key']] : [];
            $analysis[$block['key']] = [];

            foreach (array_keys($block['fields']) as $field)
            {
                $analysis[$block['key']][$field] = $this->plainText($source[$field] ?? '');
            }
        }

        return $analysis;
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    public static function hasStoredSections(array $analysis): bool
    {
        foreach (self::blocks() as $block)
        {
            $value = $analysis[$block['key']] ?? '';

            if (is_string($value) && $value !== '')
            {
                return true;
            }

            if (is_array($value) && implode('', $value) !== '')
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function hasContent(array $analysis): bool
    {
        return self::hasStoredSections($analysis);
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function readable(array $analysis): string
    {
        $lines = [];

        foreach (self::blocks() as $block)
        {
            $value = $analysis[$block['key']] ?? '';

            if (is_string($value) && $value !== '')
            {
                $lines[] = __('app.'.$block['title']).': '.$value;
            }

            if (! is_array($value))
            {
                continue;
            }

            foreach ($value as $text)
            {
                if ($text !== '')
                {
                    $lines[] = $text;
                }
            }
        }

        return implode("\n", $lines);
    }

    private function plainText(mixed $value): string
    {
        if (is_bool($value) || $value === null)
        {
            return '';
        }

        if (is_string($value) || is_numeric($value))
        {
            return trim((string) $value);
        }

        if (! is_array($value))
        {
            return '';
        }

        $parts = [];

        foreach ($value as $item)
        {
            $text = $this->plainText($item);

            if ($text !== '')
            {
                $parts[] = $text;
            }
        }

        return trim(implode(' ', $parts));
    }
}
