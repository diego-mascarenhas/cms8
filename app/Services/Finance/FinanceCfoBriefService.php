<?php

namespace App\Services\Finance;

use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\Currency;
use App\Models\EnterpriseOrganization;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\StripeSubscription;
use App\Models\Team;
use App\Services\ProjectBudgetSpecService;
use App\Support\AiTasks;
use App\Support\RevisionAlphaOrganization;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Analytics\Facades\Analytics;
use Spatie\Analytics\Period;
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
dagmar es un texto: un objetivo medible de captación o conversión, con la cifra actual y la meta. Si hay google_analytics, usa visitantes o páginas vistas.
actions es una lista de como máximo 4 textos. Cada texto empieza por un verbo, nombra el objeto y lleva la cifra que lo justifica. No uses objetos ni listas dentro de actions.
Si google_analytics es null, no hables de visitas ni de países.
publications son las publicaciones de la agenda. Si posts_last_90_days es 0, di que todavía no hay publicaciones en la agenda. Si hay, di la frecuencia por semana. No inventes likes ni alcance: no hay resultado por publicación, solo el calendario y, si existe, Google.
projects son proyectos en curso. date_end es la fecha de fin. Una cuota de payment_plan se cobra en su fecha. unscheduled_remaining se cobra en date_end y no se suma otra vez a las cuotas. annual_renewals son suscripciones anuales activas: el importe ya está en la moneda del contexto y cae en next_billing, que es el fin del periodo. Los ingresos de los meses siguientes no repiten el ritmo medio. Los gastos de un mes sin facturas de compra sí repiten el gasto medio de los meses cerrados. No sumes importes en otra moneda.
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

    private function projectionKey(int $year): string
    {
        return 'finance_cfo_projection_'.$year;
    }

    /**
     * @return array{
     *     currency: string,
     *     avg_monthly_income: float,
     *     avg_monthly_expense: float,
     *     avg_monthly_profit: float,
     *     chart_income: float,
     *     chart_expense: float,
     *     year_profit: float,
     *     months_with_data: int,
     *     contracted_income: float,
     *     contracted_expense: float,
     *     points: list<array{label: string, income: float, expense: float, projected: bool, expense_projected: bool}>,
     *     generated_at: string|null
     * }
     */
    public function storedProjection(Team $team, int $year): array
    {
        $stored = $team->getSetting($this->projectionKey($year));

        if (! is_array($stored) || ! isset($stored['points']) || ! is_array($stored['points']))
        {
            return [
                'currency' => '',
                'avg_monthly_income' => 0.0,
                'avg_monthly_expense' => 0.0,
                'avg_monthly_profit' => 0.0,
                'chart_income' => 0.0,
                'chart_expense' => 0.0,
                'year_profit' => 0.0,
                'months_with_data' => 0,
                'contracted_income' => 0.0,
                'contracted_expense' => 0.0,
                'points' => [],
                'generated_at' => null,
            ];
        }

        return $stored;
    }

    /**
     * @return array<string, mixed>
     */
    public function storeProjection(Team $team, int $year): array
    {
        $projection = $this->projection($team, $year);
        $projection['generated_at'] = now()->toIso8601String();

        $team->setSetting($this->projectionKey($year), $projection, [
            'type' => 'json',
            'group' => 'finance',
        ]);

        return $projection;
    }

    /**
     * @param  array<string, mixed>  $projection
     * @return array<string, mixed>
     */
    public function withSalaryForecast(array $projection, Team $team): array
    {
        $forecast = $this->salaryForecast($team);
        $projection['salaries'] = $forecast['people'];
        $projection['salary_monthly'] = $forecast['monthly'];

        if ($forecast['monthly'] <= 0.0 || ($projection['points'] ?? []) === [])
        {
            return $projection;
        }

        foreach ($projection['points'] as $index => $point)
        {
            $projection['points'][$index]['expense'] = round((float) ($point['expense'] ?? 0) + $forecast['monthly'], 2);
        }

        $projection['chart_expense'] = round((float) array_sum(array_column($projection['points'], 'expense')), 2);
        $projection['year_profit'] = round((float) ($projection['chart_income'] ?? 0) - (float) $projection['chart_expense'], 2);

        return $projection;
    }

    /**
     * @return array{monthly: float, people: list<array{name: string, assigned_hours: float, capacity_hours: float|null, monthly_salary: float|null, load_cost: float}>}
     */
    public function salaryForecast(Team $team): array
    {
        $people = [];
        $monthly = 0.0;

        foreach ($this->departmentContext($team)['people'] as $person)
        {
            if (($person['kind'] ?? 'internal') !== 'internal')
            {
                continue;
            }

            $assigned = (float) ($person['assigned_hours_month'] ?? 0);
            $capacity = isset($person['capacity_hours_month']) && $person['capacity_hours_month'] !== null
                ? (float) $person['capacity_hours_month']
                : null;
            $salary = isset($person['monthly_salary']) && is_numeric($person['monthly_salary'])
                ? (float) $person['monthly_salary']
                : null;
            $loadCost = $this->salaryLoadCost($salary, $assigned, $capacity);

            if ($assigned <= 0 && $loadCost <= 0)
            {
                continue;
            }

            $monthly += $loadCost;
            $people[] = [
                'name' => (string) ($person['name'] ?? ''),
                'assigned_hours' => round($assigned, 1),
                'capacity_hours' => $capacity,
                'monthly_salary' => $salary,
                'load_cost' => $loadCost,
            ];
        }

        return [
            'monthly' => round($monthly, 2),
            'people' => $people,
        ];
    }

    public function salaryLoadCost(?float $salary, float $assignedHours, ?float $capacityHours): float
    {
        if ($salary === null || $salary <= 0)
        {
            return 0.0;
        }

        if ($capacityHours !== null && $capacityHours > 0 && $assignedHours > 0)
        {
            return round($salary / $capacityHours * $assignedHours, 2);
        }

        return round($salary, 2);
    }

    private function closedMonthExpenseAverage(int $teamId, int $year): float
    {
        $report = $this->invoiceAnalyticsService->buildYearReport($teamId, $year);
        $cursor = now()->startOfMonth();
        $expenses = [];

        foreach ($report['monthly_trend'] ?? [] as $row)
        {
            $isOpenOrFuture = (int) ($report['year'] ?? 0) === (int) $cursor->year
                && (int) ($row['month'] ?? 0) >= (int) $cursor->month;

            if ($isOpenOrFuture || (float) ($row['expense'] ?? 0) <= 0)
            {
                continue;
            }

            $expenses[] = (float) $row['expense'];
        }

        if ($expenses === [])
        {
            return 0.0;
        }

        return array_sum($expenses) / count($expenses);
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
     * The open month keeps what is already invoiced and adds work dated today or
     * later. Later months do not repeat the average: they place unpaid
     * installments, project balances on the finish date, and annual renewals.
     *
     * @return array{
     *     currency: string,
     *     avg_monthly_income: float,
     *     avg_monthly_expense: float,
     *     avg_monthly_profit: float,
     *     chart_income: float,
     *     chart_expense: float,
     *     year_profit: float,
     *     months_with_data: int,
     *     contracted_income: float,
     *     contracted_expense: float,
     *     points: list<array{label: string, income: float, expense: float, projected: bool, expense_projected: bool}>
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

        if ($avgExpense <= 0.0)
        {
            $avgExpense = $this->closedMonthExpenseAverage($team->id, $year - 1);
        }

        $horizonStart = now()->startOfDay();
        $horizonEnd = $cursor->copy()->addMonths(11)->endOfMonth();
        $span = max(1, (int) $horizonStart->copy()->startOfMonth()->diffInMonths($horizonEnd->copy()->startOfMonth()) + 1);
        $plans = $this->contractedIncomeByMonth($this->openInstallments($team), $horizonStart, $span);
        $finishes = $this->contractedIncomeByMonth($this->projectFinishes($team), $horizonStart, $span);
        $renewals = $this->annualRenewalsByMonth($team, $horizonStart, $horizonEnd);
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
            $key = $date->format('Y-m');
            $extraIncome = (float) ($plans[$key] ?? 0)
                + (float) ($finishes[$key] ?? 0)
                + (float) ($renewals['income'][$key] ?? 0);
            $extraExpense = (float) ($renewals['expense'][$key] ?? 0);
            $bookedExpense = $hasActual ? (float) $monthRow['expense'] : 0.0;
            $baseExpense = $bookedExpense > 0 ? $bookedExpense : $avgExpense;

            $points[] = [
                'label' => $date->translatedFormat('M Y'),
                'income' => round(($hasActual ? (float) $monthRow['income'] : 0.0) + $extraIncome, 2),
                'expense' => round($baseExpense + $extraExpense, 2),
                'projected' => ! $hasActual,
                'expense_projected' => $bookedExpense <= 0,
            ];
        }

        $chartIncome = array_sum(array_column($points, 'income'));
        $chartExpense = array_sum(array_column($points, 'expense'));

        return [
            'currency' => (string) ($report['reporting_currency'] ?? ''),
            'avg_monthly_income' => round($avgIncome, 2),
            'avg_monthly_expense' => round($avgExpense, 2),
            'avg_monthly_profit' => round($avgIncome - $avgExpense, 2),
            'chart_income' => round($chartIncome, 2),
            'chart_expense' => round($chartExpense, 2),
            'year_profit' => round($chartIncome - $chartExpense, 2),
            'months_with_data' => $monthsWithData,
            'contracted_income' => round(array_sum($plans) + array_sum($finishes) + array_sum($renewals['income']), 2),
            'contracted_expense' => round(array_sum($renewals['expense']), 2),
            'points' => $points,
        ];
    }

    /**
     * @param  list<array{due_date: string, amount: float, charged: bool}>  $installments
     * @return array<string, float>
     */
    public function contractedIncomeByMonth(array $installments, Carbon $from, int $months): array
    {
        $end = $from->copy()->addMonths(max(0, $months - 1))->endOfMonth();
        $totals = [];

        foreach ($installments as $row)
        {
            if (($row['charged'] ?? false) === true || ! isset($row['due_date']))
            {
                continue;
            }

            $due = Carbon::parse($row['due_date']);

            if ($due->lt($from) || $due->gt($end))
            {
                continue;
            }

            $key = $due->format('Y-m');
            $totals[$key] = ($totals[$key] ?? 0) + (float) ($row['amount'] ?? 0);
        }

        return $totals;
    }

    /**
     * @return list<array{due_date: string, amount: float, charged: bool}>
     */
    private function projectFinishes(Team $team): array
    {
        $projects = Project::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereIn('status_id', $this->pipelineStatusIds())
            ->whereNotNull('date_end')
            ->get();

        $rows = [];

        foreach ($projects as $project)
        {
            $plan = $this->paymentPlan($project);
            $planned = array_sum(array_column($plan, 'amount'));
            $deposit = is_array(data_get($project->data, 'deposit_invoice'))
                && isset(data_get($project->data, 'deposit_invoice')['invoice_id'])
                ? (float) data_get($project->data, 'deposit_invoice.deposit_base', 0)
                : 0.0;
            $remaining = round(max(0, $this->quotedAmount($project) - $deposit - $planned), 2);

            if ($remaining <= 0 || $project->date_end === null)
            {
                continue;
            }

            $rows[] = [
                'due_date' => $project->date_end->toDateString(),
                'amount' => $remaining,
                'charged' => false,
            ];
        }

        return $rows;
    }

    /**
     * Active services billed every 12 months. A past next_billing rolls forward
     * one year at a time until it falls inside the horizon.
     *
     * @return array{income: array<string, float>, expense: array<string, float>}
     */
    private function annualRenewalsByMonth(Team $team, Carbon $from, Carbon $end): array
    {
        $income = [];
        $expense = [];

        foreach ($this->annualSubscriptionRows($team) as $service)
        {
            $when = $service['next_billing']->copy();
            $guard = 0;

            while ($when->lt($from) && $guard < 40)
            {
                $when->addMonthsNoOverflow(12);
                $guard++;
            }

            while ($when->lte($end) && $guard < 80)
            {
                $key = $when->format('Y-m');
                $bucket = $service['expense'] ? 'expense' : 'income';

                if ($bucket === 'expense')
                {
                    $expense[$key] = round(($expense[$key] ?? 0) + $service['amount'], 2);
                } else
                {
                    $income[$key] = round(($income[$key] ?? 0) + $service['amount'], 2);
                }

                $when->addMonthsNoOverflow(12);
                $guard++;
            }
        }

        return [
            'income' => $income,
            'expense' => $expense,
        ];
    }

    /**
     * @return list<array{name: string, operation: string, amount: float, next_billing: string}>
     */
    public function annualRenewals(Team $team): array
    {
        $from = now()->startOfDay();
        $end = now()->startOfMonth()->addMonths(11)->endOfMonth();
        $rows = [];

        foreach ($this->annualSubscriptionRows($team) as $service)
        {
            $when = $service['next_billing']->copy();
            $guard = 0;

            while ($when->lt($from) && $guard < 40)
            {
                $when->addMonthsNoOverflow(12);
                $guard++;
            }

            if ($when->gt($end))
            {
                continue;
            }

            $rows[] = [
                'name' => $service['name'],
                'operation' => $service['expense'] ? 'buy' : 'sell',
                'amount' => $service['amount'],
                'next_billing' => $when->toDateString(),
            ];
        }

        return $rows;
    }

    /**
     * Active annual subscriptions. The amount is the stored total in the team
     * currency when Stripe already converted it.
     *
     * @return list<array{name: string, amount: float, expense: bool, next_billing: Carbon}>
     */
    private function annualSubscriptionRows(Team $team): array
    {
        $reporting = $this->reportingCurrency($team);
        $subscriptions = StripeSubscription::query()
            ->where('team_id', $team->id)
            ->whereRaw("LOWER(TRIM(status)) in ('active', 'past_due', 'trialing')")
            ->where(function ($query): void
            {
                $query->where('cancel_at_period_end', false)
                    ->orWhereNull('cancel_at_period_end');
            })
            ->whereNotNull('current_period_end')
            ->where(function ($query): void
            {
                $query->where(function ($annual): void
                {
                    $annual->where('plan_interval', 'year')
                        ->where(function ($count): void
                        {
                            $count->where('plan_interval_count', 1)
                                ->orWhereNull('plan_interval_count');
                        });
                })->orWhere(function ($annual): void
                {
                    $annual->where('plan_interval', 'month')
                        ->where('plan_interval_count', 12);
                });
            })
            ->get();

        $rows = [];

        foreach ($subscriptions as $subscription)
        {
            if ($subscription->current_period_end === null)
            {
                continue;
            }

            $amount = $this->subscriptionAmount($subscription, $reporting);

            if ($amount === null || $amount <= 0)
            {
                continue;
            }

            $rows[] = [
                'name' => $subscription->clientFacingName(),
                'amount' => $amount,
                'expense' => strtolower((string) $subscription->type) === 'buy',
                'next_billing' => $subscription->current_period_end->copy(),
            ];
        }

        return $rows;
    }

    private function subscriptionAmount(StripeSubscription $subscription, string $reporting): ?float
    {
        $stored = match ($reporting)
        {
            'EUR' => $subscription->amount_eur,
            'USD' => $subscription->amount_usd,
            'ARS' => $subscription->amount_ars,
            default => null,
        };

        if ($stored !== null && (float) $stored > 0)
        {
            return round((float) $stored, 2);
        }

        $native = (float) $subscription->amount_total;
        $from = strtoupper(trim((string) $subscription->price_currency));

        if ($native <= 0 || $from === '')
        {
            return null;
        }

        return $this->toReportingCurrency($native, $from, $reporting, $subscription->current_period_end ?? now());
    }

    private function reportingCurrency(Team $team): string
    {
        return app(PaymentReportingCurrencyService::class)->reportingCurrencyForTeam($team);
    }

    /**
     * Services still store the old moneda id (1 peso, 2 dólar). Invoices store the currency id.
     */
    private function currencyCode(?int $currencyId, string $fallback): string
    {
        if ($currencyId === null || $currencyId <= 0)
        {
            return $fallback;
        }

        $code = Currency::query()->whereKey($currencyId)->value('code');

        if (filled($code))
        {
            return strtoupper((string) $code);
        }

        $mapped = app(InvoiceCurrencyService::class)->legacyMonedaIdToCurrencyId($currencyId);

        if ($mapped === $currencyId)
        {
            return $fallback;
        }

        $code = Currency::query()->whereKey($mapped)->value('code');

        return filled($code) ? strtoupper((string) $code) : $fallback;
    }

    private function toReportingCurrency(float $amount, string $from, string $to, Carbon $on): ?float
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        if ($from === '' || $from === $to)
        {
            return round($amount, 2);
        }

        $converted = ExchangeRate::convertOnOrBeforeDate($amount, $from, $to, $on);

        if ($converted !== null)
        {
            return $converted;
        }

        return ExchangeRate::convert($amount, $from, $to);
    }

    /**
     * @return list<int>
     */
    private function pipelineStatusIds(): array
    {
        return [
            ProjectStatus::STATUS_APPROVED,
            ProjectStatus::STATUS_WAITING_FOR_RESPONSE,
            ProjectStatus::STATUS_IN_PROGRESS,
            ProjectStatus::STATUS_FINISHED,
            ProjectStatus::STATUS_TO_INVOICE,
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
            'google_analytics' => $this->googleAnalytics($team),
            'publications' => $this->publications($team),
            'projects' => $this->projectPipeline($team),
            'annual_renewals' => $this->annualRenewals($team),
            'organization' => $this->departmentContext($team),
        ];
    }

    /**
     * @return array{
     *     period_days: int,
     *     visitors: int,
     *     page_views: int,
     *     new_users: int,
     *     returning_users: int,
     *     top_pages: list<array{title: string, views: int}>,
     *     top_countries: list<array{country: string, views: int}>
     * }|null
     */
    public function googleAnalytics(Team $team): ?array
    {
        $propertyId = $team->getSetting('analytics_property_id');
        $credentialsJson = $team->getSetting('analytics_credentials_json');

        if (! $propertyId || ! $credentialsJson)
        {
            return null;
        }

        $cached = Cache::get('dashboard.analytics.v4.'.$team->id);

        if (! is_array($cached) || ! isset($cached['totals']))
        {
            $cached = $this->fetchGoogleAnalytics($team, (string) $propertyId, $credentialsJson);
        }

        if (! is_array($cached) || ! isset($cached['totals']))
        {
            return null;
        }

        $totals = $cached['totals'];

        return [
            'period_days' => 30,
            'visitors' => (int) ($totals['visitors'] ?? 0),
            'page_views' => (int) ($totals['page_views'] ?? 0),
            'new_users' => (int) ($totals['new_users'] ?? 0),
            'returning_users' => (int) ($totals['returning_users'] ?? 0),
            'top_pages' => array_map(fn (array $page): array => [
                'title' => (string) ($page['title'] ?? ''),
                'views' => (int) ($page['views'] ?? 0),
            ], array_slice($cached['top_pages'] ?? [], 0, 5)),
            'top_countries' => array_map(fn (array $country): array => [
                'country' => (string) ($country['country'] ?? ''),
                'views' => (int) ($country['views'] ?? 0),
            ], array_slice($cached['top_countries'] ?? [], 0, 5)),
        ];
    }

    /**
     * @return array{
     *     posts_last_90_days: int,
     *     posts_next_90_days: int,
     *     posts_per_week_last_28_days: float,
     *     events: list<array{date: string, title: string}>
     * }
     */
    public function publications(Team $team): array
    {
        $now = now();
        $events = CalendarEvent::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('start', '>=', $now->copy()->subDays(90))
            ->where('start', '<=', $now->copy()->addDays(90))
            ->orderBy('start')
            ->get()
            ->filter(fn (CalendarEvent $event): bool => $this->isPublication($event))
            ->values();

        $recent = $events->filter(fn (CalendarEvent $event): bool => $event->start !== null && $event->start->gte($now->copy()->subDays(28)) && $event->start->lte($now));

        return [
            'posts_last_90_days' => $events->filter(fn (CalendarEvent $event): bool => $event->start !== null && $event->start->lte($now))->count(),
            'posts_next_90_days' => $events->filter(fn (CalendarEvent $event): bool => $event->start !== null && $event->start->gt($now))->count(),
            'posts_per_week_last_28_days' => round($recent->count() / 4, 1),
            'events' => $events->take(30)->map(fn (CalendarEvent $event): array => [
                'date' => $event->start?->toDateString() ?? '',
                'title' => (string) $event->title,
            ])->all(),
        ];
    }

    /**
     * @return list<array{name: string, status: string, date_end: string, quoted: float, scheduled_open: float, unscheduled_remaining: float, payment_plan: list<array{due_date: string, amount: float, charged: bool}>}>
     */
    public function projectPipeline(Team $team): array
    {
        $projects = Project::withoutGlobalScopes()
            ->with('status')
            ->where('team_id', $team->id)
            ->whereIn('status_id', $this->pipelineStatusIds())
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();

        $rows = [];

        foreach ($projects as $project)
        {
            $plan = $this->paymentPlan($project);
            $planned = array_sum(array_column($plan, 'amount'));
            $open = array_sum(array_map(
                fn (array $row): float => ($row['charged'] ?? false) ? 0.0 : (float) $row['amount'],
                $plan,
            ));
            $deposit = is_array(data_get($project->data, 'deposit_invoice'))
                && isset(data_get($project->data, 'deposit_invoice')['invoice_id'])
                ? (float) data_get($project->data, 'deposit_invoice.deposit_base', 0)
                : 0.0;

            $rows[] = [
                'name' => (string) ($project->real_name ?: $project->name),
                'status' => (string) ($project->status?->translated_name ?? $project->status?->name ?? ''),
                'date_end' => $project->date_end?->toDateString() ?? '',
                'quoted' => round($this->quotedAmount($project), 2),
                'scheduled_open' => round($open, 2),
                'unscheduled_remaining' => round(max(0, $this->quotedAmount($project) - $deposit - $planned), 2),
                'payment_plan' => $plan,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{due_date: string, amount: float, charged: bool}>
     */
    private function openInstallments(Team $team): array
    {
        $rows = [];

        $projects = Project::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereIn('status_id', $this->pipelineStatusIds())
            ->get(['id', 'data', 'status_id']);

        foreach ($projects as $project)
        {
            foreach ($this->paymentPlan($project) as $installment)
            {
                if (! $installment['charged'])
                {
                    $rows[] = $installment;
                }
            }
        }

        $reporting = $this->reportingCurrency($team);
        $invoiceIds = array_values(array_filter(array_map(
            fn (array $row): ?int => $row['invoice_id'] ?? null,
            $rows,
        )));
        $currencyIds = $invoiceIds === []
            ? collect()
            : Invoice::withoutGlobalScopes()->whereIn('id', $invoiceIds)->pluck('currency_id', 'id');
        $converted = [];

        foreach ($rows as $row)
        {
            $currencyId = isset($row['invoice_id']) ? $currencyIds->get($row['invoice_id']) : null;
            $amount = $this->toReportingCurrency(
                (float) $row['amount'],
                $this->currencyCode($currencyId !== null ? (int) $currencyId : null, $reporting),
                $reporting,
                Carbon::parse($row['due_date']),
            );

            if ($amount === null)
            {
                continue;
            }

            $converted[] = [
                'due_date' => $row['due_date'],
                'amount' => $amount,
                'charged' => false,
            ];
        }

        return $converted;
    }

    /**
     * @return list<array{due_date: string, amount: float, charged: bool, invoice_id: ?int}>
     */
    private function paymentPlan(Project $project): array
    {
        $stored = data_get($project->data, 'balance_invoices');

        if (! is_array($stored))
        {
            return [];
        }

        $rows = [];

        foreach ($stored as $row)
        {
            if (! is_array($row) || empty($row['due_date']))
            {
                continue;
            }

            $rows[] = [
                'due_date' => (string) $row['due_date'],
                'amount' => round((float) ($row['total_with_vat'] ?? $row['amount'] ?? 0), 2),
                'charged' => (bool) ($row['charged'] ?? false),
                'invoice_id' => isset($row['invoice_id']) ? (int) $row['invoice_id'] : null,
            ];
        }

        return $rows;
    }

    private function quotedAmount(Project $project): float
    {
        try
        {
            $totals = app(ProjectBudgetSpecService::class)->computeQuoteTotals($project);
            $payable = (float) ($totals['payable_total'] ?? 0);

            if ($payable > 0)
            {
                return $payable;
            }
        } catch (Throwable)
        {
            // A project without a quote still exposes its price.
        }

        return is_numeric($project->price) ? (float) $project->price : 0.0;
    }

    private function isPublication(CalendarEvent $event): bool
    {
        $label = mb_strtolower(trim((string) $event->label));

        if (in_array($label, ['publicación', 'publicacion', 'publication', 'post'], true))
        {
            return true;
        }

        $text = mb_strtolower((string) $event->title.' '.(string) $event->notes);

        return preg_match('/publicaci[oó]n|\bposts?\b|instagram|linkedin|facebook|tiktok|\breel\b|\brrss\b/u', $text) === 1;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchGoogleAnalytics(Team $team, string $propertyId, mixed $credentialsJson): ?array
    {
        $credentials = is_string($credentialsJson) ? json_decode($credentialsJson, true) : $credentialsJson;

        if (! is_array($credentials))
        {
            return null;
        }

        config([
            'analytics.property_id' => $propertyId,
            'analytics.service_account_credentials_json' => $credentials,
        ]);

        try
        {
            $period = Period::days(30);
            $collection = Analytics::fetchTotalVisitorsAndPageViews($period, 30);
            $visitors = $collection->pluck('activeUsers')->map(fn ($value) => (int) $value)->all();
            $pageViews = $collection->pluck('screenPageViews')->map(fn ($value) => (int) $value)->all();
            $payload = [
                'totals' => [
                    'visitors' => array_sum($visitors),
                    'page_views' => array_sum($pageViews),
                    'new_users' => 0,
                    'returning_users' => 0,
                ],
                'top_pages' => Analytics::fetchMostVisitedPages($period, 5)
                    ->map(fn ($row): array => [
                        'title' => (string) ($row['pageTitle'] ?? ''),
                        'views' => (int) ($row['screenPageViews'] ?? 0),
                    ])->values()->all(),
                'top_countries' => Analytics::fetchTopCountries($period, 5)
                    ->map(fn ($row): array => [
                        'country' => (string) ($row['country'] ?? ''),
                        'views' => (int) ($row['screenPageViews'] ?? 0),
                    ])->values()->all(),
            ];
        } catch (Throwable $e)
        {
            Log::warning('CFO Google Analytics fetch failed: '.$e->getMessage());

            return null;
        }

        Cache::put('dashboard.analytics.v4.'.$team->id, $payload, 3600);

        return $payload;
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
