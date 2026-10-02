<?php

namespace App\Http\Controllers;

use App\Models\BankStatement;
use App\Models\Invoice;
use App\Models\Team;
use App\Models\TeamSetting;
use App\Services\Finance\PaymentAccountStatementUploadService;
use App\Services\Finance\PaymentReportingCurrencyService;
use App\Services\Finance\VatHaciendaCsvExportService;
use App\Services\Finance\VatReportingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HaciendaPublicController extends Controller
{
    public function __construct(
        private readonly VatReportingService $vatReportingService,
        private readonly VatHaciendaCsvExportService $vatHaciendaCsvExportService,
        private readonly PaymentReportingCurrencyService $paymentReportingCurrencyService,
        private readonly PaymentAccountStatementUploadService $statementUploadService,
    ) {}

    public function show(Request $request, string $hash): View
    {
        $team = $this->teamFromHash($hash);
        $vatSelection = $this->selectedPeriod($request, $team);
        $reportingCurrency = strtoupper($this->paymentReportingCurrencyService->reportingCurrencyForTeam($team));
        $presentation = $this->vatHaciendaCsvExportService->presentationBooks(
            (int) $team->id,
            $vatSelection['range']['from'],
            $vatSelection['range']['to'],
            $reportingCurrency,
        );

        $books = $presentation['books'];
        $outputVat = round($books['sell']['summary']['tax'] + $books['sell_credit_notes']['summary']['tax'], 2);
        $inputVat = round($books['buy']['summary']['tax'] + $books['buy_credit_notes']['summary']['tax'], 2);

        return view('hacienda.public', [
            'hash' => $hash,
            'teamName' => (string) $team->name,
            'periodLabel' => $vatSelection['label'],
            'vatYears' => $vatSelection['years'],
            'vatYear' => $vatSelection['year'],
            'vatPeriod' => $vatSelection['period'],
            'headers' => $presentation['headers'],
            'books' => $books,
            'reportingCurrency' => $reportingCurrency,
            'outputVat' => $outputVat,
            'inputVat' => $inputVat,
            'vatBalance' => round($outputVat - $inputVat, 2),
            'statements' => $this->statementsForPeriod($team, $vatSelection['range']['from'], $vatSelection['range']['to'], $hash),
        ]);
    }

    public function statement(string $hash, int $statement): StreamedResponse
    {
        $team = $this->teamFromHash($hash);
        $model = BankStatement::query()
            ->where('team_id', $team->id)
            ->whereKey($statement)
            ->first();

        abort_unless($model instanceof BankStatement && $model->isDownloadable(), 404);

        try
        {
            return $this->statementUploadService->downloadStream($model);
        } catch (\RuntimeException)
        {
            abort(404);
        }
    }

    public function export(Request $request, string $hash): BinaryFileResponse
    {
        $team = $this->teamFromHash($hash);
        $vatSelection = $this->selectedPeriod($request, $team);
        $periodSlug = Str::slug($vatSelection['label']) ?: $vatSelection['range']['from']->format('Y-m');

        return $this->vatHaciendaCsvExportService->downloadPeriodZip(
            (int) $team->id,
            $vatSelection['range']['from'],
            $vatSelection['range']['to'],
            $periodSlug,
            strtoupper($this->paymentReportingCurrencyService->reportingCurrencyForTeam($team)),
        );
    }

    public function file(string $hash, int $invoice): StreamedResponse
    {
        $team = $this->teamFromHash($hash);
        $model = Invoice::withoutGlobalScopes()
            ->with([
                'team',
                'enterprise',
                'stripeInvoiceSync',
                'payments' => function ($query): void
                {
                    $query->withoutGlobalScopes()->select('id', 'invoice_id', 'remarks');
                },
            ])
            ->where('team_id', $team->id)
            ->whereKey($invoice)
            ->first();

        abort_unless($model instanceof Invoice, 404);

        return $this->vatHaciendaCsvExportService->downloadPublicDocument($model);
    }

    /**
     * @return list<array{account: string, period: string, filename: string, book: float, statement: float|null, difference: float|null, balanced: bool, url: string}>
     */
    private function statementsForPeriod(Team $team, \Carbon\Carbon $from, \Carbon\Carbon $to, string $hash): array
    {
        return BankStatement::query()
            ->with('paymentAccount')
            ->where('team_id', $team->id)
            ->whereNotNull('storage_path')
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->orderBy('id')
            ->get()
            ->filter(fn (BankStatement $statement): bool => $statement->overlaps($from, $to))
            ->map(function (BankStatement $statement) use ($hash): array
            {
                $summary = $statement->validation_summary ?? [];

                return [
                    'account' => (string) ($statement->paymentAccount?->name ?? ''),
                    'period' => $statement->periodLabel(),
                    'filename' => (string) ($statement->original_filename ?? ''),
                    'book' => round((float) ($summary['book_total'] ?? 0), 2),
                    'statement' => array_key_exists('statement_total', $summary) && $summary['statement_total'] !== null
                        ? round((float) $summary['statement_total'], 2)
                        : null,
                    'difference' => array_key_exists('difference', $summary) && $summary['difference'] !== null
                        ? round((float) $summary['difference'], 2)
                        : null,
                    'balanced' => ($summary['balanced'] ?? false) === true,
                    'url' => route('hacienda.public.statement', ['hash' => $hash, 'statement' => $statement->id]),
                ];
            })
            ->values()
            ->all();
    }

    private function teamFromHash(string $hash): Team
    {
        $setting = TeamSetting::query()
            ->where('key', 'hacienda_share_hash')
            ->where('value', $hash)
            ->first();

        $team = $setting?->team;
        abort_unless($team instanceof Team, 404);

        return $team;
    }

    /**
     * @return array{year: int, period: string, label: string, years: list<int>, range: array{from: \Carbon\Carbon, to: \Carbon\Carbon}}
     */
    private function selectedPeriod(Request $request, Team $team): array
    {
        return $this->vatReportingService->resolveSelectedPeriod(
            year: $request->integer('vat_year') ?: null,
            period: $request->string('vat_period')->toString() ?: null,
            teamId: (int) $team->id,
        );
    }
}
