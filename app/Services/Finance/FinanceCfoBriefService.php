<?php

namespace App\Services\Finance;

use App\Models\Contact;
use App\Models\EnterpriseOrganization;
use App\Models\Team;
use App\Support\AiTasks;
use App\Support\RevisionAlphaOrganization;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Laravel\Ai\agent;

class FinanceCfoBriefService
{
    public function __construct(
        private readonly InvoiceAnalyticsService $invoiceAnalyticsService,
    ) {}

    /**
     * @return array{
     *     dafo: array{fortalezas: string, debilidades: string, oportunidades: string, amenazas: string},
     *     fifo: string,
     *     dagmar: string,
     *     actions: list<string>,
     *     capacity: array{resources: string, hours: string, minimum_salary: string, now: string, missing_departments: string},
     *     brief: string,
     *     generated_at: string
     * }|null
     */
    public function storedAnalysis(Team $team, int $year): ?array
    {
        $stored = $team->getSetting($this->settingKey($year));

        if (! is_array($stored))
        {
            return null;
        }

        $analysis = $this->normalize($stored);
        $hasPerspective = $analysis['fifo'] !== ''
            || $analysis['dagmar'] !== ''
            || $analysis['actions'] !== []
            || implode('', $analysis['dafo']) !== ''
            || implode('', $analysis['capacity']) !== '';

        if (! $hasPerspective && $analysis['brief'] === '')
        {
            return null;
        }

        return $analysis;
    }

    public function storedBrief(Team $team, int $year): ?string
    {
        return $this->storedAnalysis($team, $year)['brief'] ?? null;
    }

    /**
     * @return list<string>
     */
    public function actions(Team $team, int $year): array
    {
        return $this->storedAnalysis($team, $year)['actions'] ?? [];
    }

    public function remember(Team $team, int $year, bool $refresh = false): string
    {
        if (! $refresh)
        {
            $stored = $this->storedBrief($team, $year);

            if ($stored !== null)
            {
                return $stored;
            }
        }

        $text = $this->suggest($team, $year);
        $failed = __('The CFO suggestion could not be generated.');

        if ($text === $failed)
        {
            return $this->storedBrief($team, $year) ?? $text;
        }

        $parsed = $this->parse($text);
        $analysis = $parsed ?? $this->normalize(['brief' => $text]);
        $analysis['brief'] = $parsed !== null ? $this->readable($analysis) : $text;
        $analysis['generated_at'] = now()->toIso8601String();

        $team->setSetting($this->settingKey($year), $analysis, [
            'type' => 'json',
            'group' => 'finance',
        ]);

        return $analysis['brief'];
    }

    public function suggest(Team $team, int $year): string
    {
        $context = $this->context($team, $year);
        $instructions = <<<'TXT'
Eres el CFO de la empresa. Responde solo con JSON válido, en español, sin markdown.
Usa únicamente los números del contexto. No inventes importes, clientes ni porcentajes. Si un dato no está, dilo en esa frase.
El JSON tiene esta forma:
{"dafo":{"fortalezas":"","debilidades":"","oportunidades":"","amenazas":""},"fifo":"","dagmar":"","actions":[],"capacity":{"resources":"","hours":"","minimum_salary":"","now":"","missing_departments":""}}
dafo es la lectura del negocio: fortalezas, debilidades, oportunidades y amenazas. Cada una es un texto, una frase con su cifra.
fifo es un texto: qué atender primero porque entró antes (cobros vencidos, leads sin convertir, gastos sin clasificar).
dagmar es un texto: un objetivo medible de captación o conversión, con la cifra actual y la meta.
actions es una lista de como máximo 4 textos. Cada texto empieza por un verbo, nombra el objeto y lleva la cifra que lo justifica. No uses objetos ni listas dentro de actions.
capacity son textos, no objetos anidados.
resources dice qué gente o capacidad hace falta, con las horas del contexto.
hours compara horas asignadas, capacidad y huecos. Si no hay horas, dilo.
minimum_salary dice el salario mínimo que aguanta el margen. Usa solo la nómina o el salario del contexto. Si no hay salario cargado, no inventes una cifra.
now dice qué se puede entregar ya con los departamentos actuales.
missing_departments dice si hace falta otro departamento y cuál. Si los actuales cubren el trabajo, di que no hace falta abrir otro.
TXT;

        $userMessage = "CONTEXTO FINANCIERO\n\n".json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

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
                60,
            );
            $text = trim((string) ($response->text ?? ''));
        } catch (Throwable $e)
        {
            Log::error('FinanceCfoBriefService::suggest failed', ['error' => $e->getMessage()]);

            return __('The CFO suggestion could not be generated.');
        }

        if ($text === '')
        {
            return __('The CFO suggestion could not be generated.');
        }

        return $text;
    }

    private function settingKey(int $year): string
    {
        return 'finance_cfo_brief_'.$year;
    }

    /**
     * @return array{
     *     dafo: array{fortalezas: string, debilidades: string, oportunidades: string, amenazas: string},
     *     fifo: string,
     *     dagmar: string,
     *     actions: list<string>,
     *     capacity: array{resources: string, hours: string, minimum_salary: string, now: string, missing_departments: string},
     *     brief: string,
     *     generated_at: string
     * }|null
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
     * @return array{
     *     dafo: array{fortalezas: string, debilidades: string, oportunidades: string, amenazas: string},
     *     fifo: string,
     *     dagmar: string,
     *     actions: list<string>,
     *     capacity: array{resources: string, hours: string, minimum_salary: string, now: string, missing_departments: string},
     *     brief: string,
     *     generated_at: string
     * }
     */
    private function normalize(array $payload): array
    {
        $dafo = is_array($payload['dafo'] ?? null) ? $payload['dafo'] : [];
        $capacity = is_array($payload['capacity'] ?? null) ? $payload['capacity'] : [];
        $rawActions = $payload['actions'] ?? [];

        if (is_string($rawActions) || is_numeric($rawActions))
        {
            $rawActions = [$rawActions];
        }

        if (! is_array($rawActions))
        {
            $rawActions = [];
        }

        $actions = [];

        foreach ($rawActions as $action)
        {
            $action = $this->plainText($action);

            if ($action !== '')
            {
                $actions[] = $action;
            }
        }

        return [
            'dafo' => [
                'fortalezas' => $this->plainText($dafo['fortalezas'] ?? ''),
                'debilidades' => $this->plainText($dafo['debilidades'] ?? ''),
                'oportunidades' => $this->plainText($dafo['oportunidades'] ?? ''),
                'amenazas' => $this->plainText($dafo['amenazas'] ?? ''),
            ],
            'fifo' => $this->plainText($payload['fifo'] ?? ''),
            'dagmar' => $this->plainText($payload['dagmar'] ?? ''),
            'actions' => array_slice($actions, 0, 4),
            'capacity' => [
                'resources' => $this->plainText($capacity['resources'] ?? ''),
                'hours' => $this->plainText($capacity['hours'] ?? ''),
                'minimum_salary' => $this->plainText($capacity['minimum_salary'] ?? ''),
                'now' => $this->plainText($capacity['now'] ?? ''),
                'missing_departments' => $this->plainText($capacity['missing_departments'] ?? ''),
            ],
            'brief' => $this->plainText($payload['brief'] ?? ''),
            'generated_at' => $this->plainText($payload['generated_at'] ?? ''),
        ];
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

    /**
     * @param  array{
     *     dafo: array{fortalezas: string, debilidades: string, oportunidades: string, amenazas: string},
     *     fifo: string,
     *     dagmar: string,
     *     actions: list<string>,
     *     capacity: array{resources: string, hours: string, minimum_salary: string, now: string, missing_departments: string},
     *     brief: string,
     *     generated_at: string
     * }  $analysis
     */
    private function readable(array $analysis): string
    {
        $lines = [];

        foreach ([
            'fortalezas' => 'Fortalezas',
            'debilidades' => 'Debilidades',
            'oportunidades' => 'Oportunidades',
            'amenazas' => 'Amenazas',
        ] as $key => $label)
        {
            if ($analysis['dafo'][$key] !== '')
            {
                $lines[] = $label.': '.$analysis['dafo'][$key];
            }
        }

        if ($analysis['fifo'] !== '')
        {
            $lines[] = 'FIFO: '.$analysis['fifo'];
        }

        if ($analysis['dagmar'] !== '')
        {
            $lines[] = 'DAGMAR: '.$analysis['dagmar'];
        }

        foreach ($analysis['actions'] as $action)
        {
            $lines[] = $action;
        }

        foreach ([
            'resources' => 'Recursos',
            'hours' => 'Horas',
            'minimum_salary' => 'Salario mínimo',
            'now' => 'Ahora',
            'missing_departments' => 'Departamentos',
        ] as $key => $label)
        {
            if (($analysis['capacity'][$key] ?? '') !== '')
            {
                $lines[] = $label.': '.$analysis['capacity'][$key];
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Twelve months from the current month. A closed month keeps its real amount.
     * The open month keeps what is already invoiced. Later months repeat the
     * average of the closed months.
     *
     * @return array{
     *     currency: string,
     *     avg_monthly_income: float,
     *     avg_monthly_expense: float,
     *     avg_monthly_profit: float,
     *     year_profit: float,
     *     months_with_data: int,
     *     points: list<array{label: string, income: float, expense: float, projected: bool}>
     * }
     */
    public function projection(Team $team, int $year): array
    {
        $report = $this->invoiceAnalyticsService->buildYearReport($team->id, $year);
        $trend = $report['monthly_trend'];
        $cursor = now()->startOfMonth();
        $actual = [];

        foreach ($trend as $row)
        {
            $isOpenOrFutureThisYear = (int) $report['year'] === (int) $cursor->year
                && (int) $row['month'] >= (int) $cursor->month;

            if ($isOpenOrFutureThisYear)
            {
                continue;
            }

            if ((float) $row['income'] > 0 || (float) $row['expense'] > 0)
            {
                $actual[] = $row;
            }
        }

        $monthsWithData = count($actual);
        $avgIncome = $monthsWithData > 0
            ? array_sum(array_column($actual, 'income')) / $monthsWithData
            : 0.0;
        $avgExpense = $monthsWithData > 0
            ? array_sum(array_column($actual, 'expense')) / $monthsWithData
            : 0.0;
        $points = [];

        for ($offset = 0; $offset < 12; $offset++)
        {
            $date = $cursor->copy()->addMonths($offset);
            $monthRow = ((int) $date->year === (int) $report['year'])
                ? ($trend[$date->month - 1] ?? null)
                : null;
            $hasActual = is_array($monthRow)
                && $date->lte($cursor)
                && (((float) $monthRow['income']) > 0 || ((float) $monthRow['expense']) > 0);

            $points[] = [
                'label' => $date->translatedFormat('M Y'),
                'income' => round($hasActual ? (float) $monthRow['income'] : $avgIncome, 2),
                'expense' => round($hasActual ? (float) $monthRow['expense'] : $avgExpense, 2),
                'projected' => ! $hasActual,
            ];
        }

        return [
            'currency' => (string) ($report['reporting_currency'] ?? ''),
            'avg_monthly_income' => round($avgIncome, 2),
            'avg_monthly_expense' => round($avgExpense, 2),
            'avg_monthly_profit' => round($avgIncome - $avgExpense, 2),
            'year_profit' => round(($avgIncome - $avgExpense) * 12, 2),
            'months_with_data' => $monthsWithData,
            'points' => $points,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Team $team, int $year): array
    {
        $report = $this->invoiceAnalyticsService->buildYearReport($team->id, $year);
        $pipeline = Contact::getContactStats($team->id);
        $enteredQuery = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereYear('created_at', $year);
        $leadsEntered = (clone $enteredQuery)->count();
        $leadsConverted = (clone $enteredQuery)->where('status_id', 5)->count();
        $payroll = [];

        foreach ($report['expense_categories'] as $category)
        {
            if (preg_match('/sueldo|nómina|nomina|salario|salary|payroll/i', (string) $category['name']) === 1)
            {
                $payroll[] = [
                    'category' => $category['name'],
                    'amount' => $category['total'],
                ];
            }
        }

        return [
            'year' => $report['year'],
            'currency' => $report['reporting_currency'],
            'income' => round($report['summary']['income'], 2),
            'expense' => round($report['summary']['expense'], 2),
            'profit' => round($report['summary']['profit'], 2),
            'margin_percent' => round($report['summary']['margin_percent'], 1),
            'contracted_services' => array_map(fn (array $category): array => [
                'category' => $category['name'],
                'amount' => $category['total'],
                'share_percent' => round($category['share_percent'], 1),
            ], $report['income_categories']),
            'expense_categories' => array_map(fn (array $category): array => [
                'category' => $category['name'],
                'amount' => $category['total'],
            ], $report['expense_categories']),
            'payroll' => $payroll,
            'leads_entered_this_year' => $leadsEntered,
            'leads_converted_to_client_this_year' => $leadsConverted,
            'open_leads' => (int) ($pipeline['totalLeads'] ?? 0),
            'clients' => (int) ($pipeline['totalClients'] ?? 0),
            'monthly_run_rate' => [
                'income' => round((float) ($report['scenario']['avg_monthly_income'] ?? 0), 2),
                'expense' => round((float) ($report['scenario']['avg_monthly_expense'] ?? 0), 2),
                'profit' => round((float) ($report['scenario']['avg_monthly_profit'] ?? 0), 2),
            ],
            'organization' => $this->departmentContext($team),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function departmentContext(Team $team): array
    {
        if (RevisionAlphaOrganization::appliesTo($team->id))
        {
            $catalog = RevisionAlphaOrganization::viewData();
            $people = [];

            foreach ($catalog['cost_rows'] as $row)
            {
                $person = is_array($row['person'] ?? null) ? $row['person'] : [];
                $people[] = [
                    'name' => (string) ($person['name'] ?? ''),
                    'kind' => (string) ($person['kind'] ?? ''),
                    'weekly_hours' => $person['weekly_hours'] ?? null,
                    'monthly_salary' => $person['monthly_salary'] ?? null,
                    'assigned_hours_month' => $row['assigned_hours'] ?? null,
                    'capacity_hours_month' => $row['capacity_hours'] ?? null,
                ];
            }

            $departments = [];
            foreach ($catalog['departments'] as $key => $department)
            {
                $departments[] = [
                    'name' => (string) ($department['name'] ?? $key),
                    'processes' => count($catalog['processes_by_department'][$key] ?? []),
                ];
            }

            return [
                'departments' => $departments,
                'people' => $people,
                'uncovered_hours_per_week' => $catalog['coverage_grid']['gap_hours_per_week'] ?? null,
            ];
        }

        $roles = EnterpriseOrganization::query()
            ->where('team_id', $team->id)
            ->with('department:id,name')
            ->orderBy('order')
            ->get()
            ->map(fn (EnterpriseOrganization $row): array => [
                'department' => $row->department?->name,
                'role' => $row->name,
                'time_allocation' => $row->time_allocation,
                'availability' => $row->availability,
            ])
            ->all();

        return [
            'departments' => [],
            'roles' => $roles,
            'people' => [],
            'uncovered_hours_per_week' => null,
        ];
    }
}
