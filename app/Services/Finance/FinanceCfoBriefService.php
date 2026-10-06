<?php

namespace App\Services\Finance;

use App\Models\Contact;
use App\Models\Team;
use App\Support\AiTasks;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Laravel\Ai\agent;

class FinanceCfoBriefService
{
    public function __construct(
        private readonly InvoiceAnalyticsService $invoiceAnalyticsService,
    ) {}

    public function suggest(Team $team, int $year): string
    {
        $context = $this->context($team, $year);
        $instructions = <<<'TXT'
Eres el CFO de la empresa. Responde en español, en 4 o 5 acciones concretas para este mes.
Usa solo los números del contexto: servicios contratados (categorías de ingreso), leads ingresados y convertidos, y sueldos.
Cada acción tiene que decir en qué trabajar y por qué, con la cifra que la sostiene.
No inventes importes, clientes ni porcentajes que no estén en el contexto. Si un dato no está, dilo y sigue con lo que sí hay.
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
        ];
    }
}
