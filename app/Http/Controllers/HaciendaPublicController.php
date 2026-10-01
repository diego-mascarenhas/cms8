<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Team;
use App\Models\TeamSetting;
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

        return view('hacienda.public', [
            'hash' => $hash,
            'teamName' => (string) $team->name,
            'periodLabel' => $vatSelection['label'],
            'vatYears' => $vatSelection['years'],
            'vatYear' => $vatSelection['year'],
            'vatPeriod' => $vatSelection['period'],
            'headers' => $presentation['headers'],
            'books' => $presentation['books'],
        ]);
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
