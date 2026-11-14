<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentAccountStatementRequest;
use App\Models\BankStatement;
use App\Models\PaymentAccount;
use App\Services\Finance\PaymentAccountStatementUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentAccountStatementController extends Controller
{
    public function __construct(
        private readonly PaymentAccountStatementUploadService $statementUploadService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PaymentAccount::class);

        $accounts = PaymentAccount::query()
            ->with('currency:id,code')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'currency_id'])
            ->map(fn (PaymentAccount $account): array => [
                'id' => (int) $account->id,
                'name' => (string) $account->name,
                'code' => (string) $account->code,
                'currency_code' => strtoupper((string) ($account->currency?->code ?? '')),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    public function store(
        StorePaymentAccountStatementRequest $request,
        PaymentAccount $paymentAccount,
    ): JsonResponse {
        $this->authorize('update', $paymentAccount);

        $validated = $request->validated();
        [$month, $quarter] = $this->statementPeriod($validated);
        $statementBalance = isset($validated['statement_balance']) && $validated['statement_balance'] !== ''
            ? (float) $validated['statement_balance']
            : null;

        $results = $this->statementUploadService->uploadMany(
            $paymentAccount,
            $validated['files'],
            isset($validated['period_year']) ? (int) $validated['period_year'] : null,
            $month,
            $quarter,
            $statementBalance,
        );

        $statements = collect($results)->map(function (array $result): array
        {
            /** @var BankStatement $statement */
            $statement = $result['statement'];
            $validation = $result['validation'] ?? [];

            return [
                'id' => (int) $statement->id,
                'filename' => (string) $statement->original_filename,
                'period_label' => $statement->periodLabel(),
                'matched_lines' => (int) ($validation['matched'] ?? 0),
                'statement_only_lines' => (int) ($validation['statement_only'] ?? 0),
                'payment_only_lines' => (int) ($validation['payment_only'] ?? 0),
                'balanced' => (bool) ($validation['balanced'] ?? false),
                'difference' => array_key_exists('difference', $validation) && $validation['difference'] !== null
                    ? round((float) $validation['difference'], 2)
                    : null,
                'book_total' => array_key_exists('book_total', $validation) && $validation['book_total'] !== null
                    ? round((float) $validation['book_total'], 2)
                    : null,
                'statement_total' => array_key_exists('statement_total', $validation) && $validation['statement_total'] !== null
                    ? round((float) $validation['statement_total'], 2)
                    : null,
            ];
        })->values()->all();

        $uploaded = count($statements);
        $balanced = collect($statements)->where('balanced', true)->count();
        $open = collect($statements)->filter(function (array $row): bool
        {
            $difference = $row['difference'] ?? null;

            return $difference !== null && abs((float) $difference) > 0.05;
        })->count();

        return response()->json([
            'success' => true,
            'uploaded' => $uploaded,
            'balanced' => $balanced,
            'open' => $open,
            'statements' => $statements,
            'message' => __(':count extracto(s) subido(s). Empatados: :balanced. Con diferencia: :open.', [
                'count' => $uploaded,
                'balanced' => $balanced,
                'open' => $open,
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: int|null, 1: int|null}
     */
    private function statementPeriod(array $validated): array
    {
        $period = (string) ($validated['period'] ?? '');

        if (preg_match('/^m:(\d{1,2})$/', $period, $matches) === 1)
        {
            return [(int) $matches[1], null];
        }

        if (preg_match('/^q:([1-4])$/', $period, $matches) === 1)
        {
            return [null, (int) $matches[1]];
        }

        return [
            isset($validated['period_month']) ? (int) $validated['period_month'] : null,
            null,
        ];
    }
}
