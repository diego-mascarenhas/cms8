<?php

namespace App\Services\Finance;

use App\Models\Contact;
use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceType;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Services\ProjectBudgetSpecService;
use App\Support\DatabaseSequence;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectCollaboratorProformaService
{
    public const DEFAULT_PERCENT = 30.0;

    public const REFERENCE_PREFIX = 'project-participant-';

    public function __construct(
        private readonly ProjectBudgetSpecService $budgetSpecService,
    ) {}

    /**
     * @return array{
     *     payable_total: int,
     *     default_percent: float,
     *     collaborators: list<array{
     *         user_id: int,
     *         name: string,
     *         enterprise_id: int|null,
     *         enterprise_name: string|null,
     *         already_issued: bool,
     *         invoice_id: int|null,
     *         amount: float|null,
     *         mode: string|null,
     *         value: float|null
     *     }>
     * }
     */
    public function preview(Project $project): array
    {
        $project->loadMissing(['participants.contact.currentEnterprise', 'participants.contact.enterprises', 'responsible.contact.currentEnterprise', 'responsible.contact.enterprises']);
        $payable = (int) $this->budgetSpecService->computeQuoteTotals($project)['payable_total'];
        $stored = $this->storedProformas($project);
        $rows = [];

        foreach ($this->teamCollaborators($project) as $user)
        {
            $userId = (int) $user->id;
            $existing = is_array($stored[$userId] ?? null) ? $stored[$userId] : (is_array($stored[(string) $userId] ?? null) ? $stored[(string) $userId] : null);
            $enterprise = $this->resolveEnterpriseForUser($user);

            $rows[] = [
                'user_id' => $userId,
                'name' => (string) $user->name,
                'enterprise_id' => $enterprise?->id,
                'enterprise_name' => $enterprise?->name,
                'already_issued' => isset($existing['invoice_id']),
                'invoice_id' => isset($existing['invoice_id']) ? (int) $existing['invoice_id'] : null,
                'amount' => isset($existing['amount']) ? (float) $existing['amount'] : null,
                'mode' => isset($existing['mode']) ? (string) $existing['mode'] : null,
                'value' => isset($existing['value']) ? (float) $existing['value'] : null,
            ];
        }

        return [
            'payable_total' => $payable,
            'default_percent' => self::DEFAULT_PERCENT,
            'collaborators' => $rows,
        ];
    }

    /**
     * Create draft purchase proformas (status 9) for the selected team collaborators.
     *
     * @param  list<array{user_id: int, mode: string, value: float}>  $lines
     * @return array{created: list<Invoice>, skipped: list<int>}
     */
    public function issue(Project $project, Team $team, array $lines): array
    {
        if ($lines === [])
        {
            throw ValidationException::withMessages([
                'collaborators' => __('Seleccioná al menos un colaborador.'),
            ]);
        }

        $preview = $this->preview($project);
        $payable = (int) $preview['payable_total'];

        if ($payable <= 0)
        {
            throw ValidationException::withMessages([
                'project' => __('El presupuesto del proyecto debe ser mayor que cero.'),
            ]);
        }

        $byUser = collect($preview['collaborators'])->keyBy('user_id');
        $created = [];
        $skipped = [];

        foreach ($lines as $line)
        {
            $userId = (int) $line['user_id'];
            $row = $byUser->get($userId);

            if ($row === null)
            {
                throw ValidationException::withMessages([
                    "collaborators.{$userId}" => __('El colaborador no pertenece a este proyecto.'),
                ]);
            }

            if (! empty($row['already_issued']))
            {
                $skipped[] = $userId;

                continue;
            }

            if (empty($row['enterprise_id']))
            {
                throw ValidationException::withMessages([
                    "collaborators.{$userId}.enterprise" => __('Vinculá una empresa a :name antes de crear la proforma.', [
                        'name' => $row['name'],
                    ]),
                ]);
            }

            $mode = (string) $line['mode'];
            $value = (float) $line['value'];
            $amount = $this->resolveAmount($payable, $mode, $value);

            if ($amount < 0.01)
            {
                throw ValidationException::withMessages([
                    "collaborators.{$userId}.value" => __('El importe debe ser mayor que cero.'),
                ]);
            }

            $created[] = $this->createProforma(
                $project,
                $team,
                $userId,
                (string) $row['name'],
                (int) $row['enterprise_id'],
                $mode,
                $value,
                $amount,
            );
        }

        if ($created === [] && $skipped !== [])
        {
            throw ValidationException::withMessages([
                'collaborators' => __('Ya existen proformas para los colaboradores seleccionados.'),
            ]);
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    public function resolveAmount(int $payableTotal, string $mode, float $value): float
    {
        if ($mode === 'amount')
        {
            return round(max($value, 0), 2);
        }

        $percent = max(0.0, min(100.0, $value));

        return round($payableTotal * ($percent / 100), 2);
    }

    public static function referenceFor(int $projectId, int $userId): string
    {
        return self::REFERENCE_PREFIX.$projectId.'-'.$userId;
    }

    /**
     * Responsible + participants, unique by user id.
     *
     * @return Collection<int, User>
     */
    public function teamCollaborators(Project $project): Collection
    {
        $users = collect();

        if ($project->responsible instanceof User)
        {
            $users->put((int) $project->responsible->id, $project->responsible);
        }

        foreach ($project->participants as $participant)
        {
            if ($participant instanceof User)
            {
                $users->put((int) $participant->id, $participant);
            }
        }

        return $users->sortBy(fn (User $user) => mb_strtolower((string) $user->name), SORT_NATURAL)->values();
    }

    private function createProforma(
        Project $project,
        Team $team,
        int $userId,
        string $userName,
        int $enterpriseId,
        string $mode,
        float $value,
        float $amount,
    ): Invoice {
        $reference = self::referenceFor((int) $project->id, $userId);
        $existing = Invoice::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('source_provider', 'manual')
            ->where('source_reference_id', $reference)
            ->first();

        if ($existing instanceof Invoice)
        {
            $this->attachProformaToProject($project, $userId, $existing, $mode, $value, $amount);

            return $existing;
        }

        $projectName = trim((string) ($project->real_name ?: $project->name));
        if ($mode === 'percent')
        {
            $percentLabel = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
            $description = __('Proforma :percent% — :project — :collaborator', [
                'percent' => $percentLabel,
                'project' => $projectName,
                'collaborator' => $userName,
            ]);
        } else
        {
            $description = __('Proforma — :project — :collaborator', [
                'project' => $projectName,
                'collaborator' => $userName,
            ]);
        }

        $today = Carbon::now()->toDateString();

        $invoice = DB::transaction(function () use (
            $team,
            $enterpriseId,
            $amount,
            $today,
            $reference,
            $description,
            $project,
        ): Invoice {
            DatabaseSequence::sync('invoices');
            DatabaseSequence::sync('invoice_items');

            $currencyId = Currency::query()->where('code', 'EUR')->value('id');
            $typeId = (int) (InvoiceType::query()->orderBy('id')->value('id') ?? 1);

            $invoice = Invoice::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'enterprise_id' => $enterpriseId,
                'billing_id' => null,
                'type_id' => $typeId,
                'operation' => 'buy',
                'number' => 'Proforma '.Carbon::now()->format('Y-m'),
                'date' => $today,
                'due_date' => $today,
                'gross_amount' => $amount,
                'discount' => 0,
                'total_amount' => $amount,
                'balance' => $amount,
                'currency_id' => $currencyId,
                'status' => 9,
                'source_provider' => 'manual',
                'source_reference_id' => $reference,
            ]);

            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'category_id' => $project->category_id ? (int) $project->category_id : null,
                'description' => mb_substr($description, 0, 255),
                'quantity' => 1,
                'unit_price' => $amount,
                'discount' => 0,
                'tax_percentage' => 0,
            ]);

            return $invoice;
        });

        $this->attachProformaToProject($project, $userId, $invoice, $mode, $value, $amount);

        return $invoice;
    }

    private function attachProformaToProject(
        Project $project,
        int $userId,
        Invoice $invoice,
        string $mode,
        float $value,
        float $amount,
    ): void {
        $data = is_array($project->data) ? $project->data : [];
        $rows = is_array($data['collaborator_proformas'] ?? null) ? $data['collaborator_proformas'] : [];
        $rows[(string) $userId] = [
            'invoice_id' => (int) $invoice->id,
            'user_id' => $userId,
            'mode' => $mode,
            'value' => $value,
            'amount' => $amount,
            'issued_at' => now()->toIso8601String(),
        ];
        $data['collaborator_proformas'] = $rows;
        $project->data = $data;
        $project->save();
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    private function storedProformas(Project $project): array
    {
        $rows = data_get($project->data, 'collaborator_proformas');

        return is_array($rows) ? $rows : [];
    }

    private function resolveEnterpriseForUser(User $user): ?Enterprise
    {
        $contact = $user->relationLoaded('contact')
            ? $user->contact
            : $user->contact()->with(['currentEnterprise', 'enterprises'])->first();

        if (! $contact instanceof Contact)
        {
            return null;
        }

        return $this->resolveEnterprise($contact);
    }

    private function resolveEnterprise(Contact $contact): ?Enterprise
    {
        if ($contact->relationLoaded('currentEnterprise') && $contact->currentEnterprise instanceof Enterprise)
        {
            return $contact->currentEnterprise;
        }

        if ($contact->current_enterprise_id)
        {
            $enterprise = Enterprise::withoutGlobalScopes()->find($contact->current_enterprise_id);
            if ($enterprise instanceof Enterprise)
            {
                return $enterprise;
            }
        }

        if ($contact->relationLoaded('enterprises'))
        {
            $first = $contact->enterprises->first();

            return $first instanceof Enterprise ? $first : null;
        }

        $first = $contact->enterprises()->first();

        return $first instanceof Enterprise ? $first : null;
    }
}
