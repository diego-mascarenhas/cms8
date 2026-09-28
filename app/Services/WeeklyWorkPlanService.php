<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Email;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\List60;
use App\Models\MessageDelivery;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Service;
use App\Models\Team;
use App\Models\User;
use App\Models\WeeklyWorkPlan;
use App\Services\Finance\InvoiceSummaryService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class WeeklyWorkPlanService
{
    /**
     * Sources, in the order they are worked. Tax filing is quarterly and only appears in its window.
     *
     * @var list<string>
     */
    private const ORDER = [
        'sales_objective',
        'company_email',
        'personal_email',
        'whatsapp',
        'projects',
        'draft_invoices',
        'overdue_invoices',
        'services',
        'tax_filing',
        'new_leads',
        'list60',
        'list60_suggestions',
    ];

    private const DETAIL_LIMIT = 30;

    /**
     * @return array{mode: string, title: string, challenge: ?string, items: list<array<string, mixed>>}
     */
    public function present(User $user, Team $team, ?CarbonInterface $today = null): array
    {
        $today = Carbon::parse($today ?? now())->startOfDay();
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $plan = WeeklyWorkPlan::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->whereDate('week_starts_on', $weekStart->toDateString())
            ->first();

        if ($plan === null)
        {
            $plan = new WeeklyWorkPlan([
                'team_id' => $team->id,
                'user_id' => $user->id,
                'week_starts_on' => $weekStart->toDateString(),
            ]);
        }

        $refreshShape = $plan->exists && $this->itemsLackBreakdown($plan->items);
        if (! $plan->exists || $today->isMonday() || $refreshShape)
        {
            $plan->challenge = $this->monthlyChallenge($team);
            $plan->items = $this->buildItems($user, $team, $weekStart, $weekEnd);
            if (! $plan->exists || $today->isMonday())
            {
                $plan->review = null;
                $plan->reviewed_at = null;
            }
            $plan->save();
        }

        if ($today->isFriday())
        {
            $plan->review = $this->buildReview($plan->items ?? [], $user, $team, $today, $weekStart, $weekEnd);
            $plan->reviewed_at = $today;
            $plan->save();
        }

        $showReview = $today->isFriday() || ($today->isWeekend() && $plan->reviewed_at !== null);
        if ($showReview && is_array($plan->review))
        {
            return [
                'mode' => 'review',
                'title' => (string) __('app.weekly_plan_review_title'),
                'challenge' => $plan->challenge,
                'items' => $plan->review,
            ];
        }

        return [
            'mode' => 'plan',
            'title' => (string) __('app.weekly_plan_title'),
            'challenge' => $plan->challenge,
            'items' => $plan->items ?? [],
        ];
    }

    /**
     * @return array{
     *     mode: string,
     *     title: string,
     *     challenge: ?string,
     *     items: list<array<string, mixed>>,
     *     week_start: string,
     *     week_label: string,
     *     previous_week: ?string,
     *     next_week: ?string,
     *     is_current: bool
     * }
     */
    public function report(User $user, Team $team, ?string $week = null): array
    {
        $current = now()->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $selected = $current;
        if (is_string($week) && $week !== '')
        {
            try
            {
                $selected = Carbon::parse($week)->startOfWeek(Carbon::MONDAY)->startOfDay();
            } catch (\Throwable)
            {
                $selected = $current;
            }
        }

        if ($selected->greaterThan($current))
        {
            $selected = $current;
        }

        if ($selected->equalTo($current))
        {
            $presented = $this->present($user, $team, $current);
        } else
        {
            $stored = WeeklyWorkPlan::query()
                ->where('team_id', $team->id)
                ->where('user_id', $user->id)
                ->whereDate('week_starts_on', $selected->toDateString())
                ->first();

            $hasReview = $stored !== null && is_array($stored->review) && $stored->review !== [];
            $presented = [
                'mode' => $hasReview ? 'review' : 'plan',
                'title' => $hasReview
                    ? (string) __('app.weekly_plan_review_title')
                    : (string) __('app.weekly_plan_title'),
                'challenge' => $stored?->challenge,
                'items' => $hasReview ? $stored->review : (is_array($stored?->items) ? $stored->items : []),
            ];
        }

        $weeks = WeeklyWorkPlan::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->orderBy('week_starts_on')
            ->pluck('week_starts_on')
            ->map(fn ($date): string => Carbon::parse($date)->startOfWeek(Carbon::MONDAY)->toDateString())
            ->push($current->toDateString())
            ->unique()
            ->sort()
            ->values();

        $selectedKey = $selected->toDateString();
        $index = $weeks->search($selectedKey);
        $previous = $index !== false && $index > 0 ? $weeks[$index - 1] : null;
        $next = $index !== false && $index < $weeks->count() - 1 ? $weeks[$index + 1] : null;

        $end = $selected->copy()->endOfWeek(Carbon::SUNDAY);

        return [
            'mode' => $presented['mode'],
            'title' => $presented['title'],
            'challenge' => $presented['challenge'],
            'items' => $presented['items'],
            'week_start' => $selectedKey,
            'week_label' => $selected->locale('es')->isoFormat('D MMM').' – '.$end->locale('es')->isoFormat('D MMM YYYY'),
            'previous_week' => $previous,
            'next_week' => $next,
            'is_current' => $selected->equalTo($current),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildItems(User $user, Team $team, CarbonInterface $weekStart, CarbonInterface $weekEnd): array
    {
        $counts = $this->counts($user, $team, $weekStart, $weekEnd);
        $people = (int) $counts['list60'] + (int) $counts['new_leads'] + (int) $counts['list60_suggestions'];
        $actions = (int) $counts['draft_invoices'] + (int) $counts['overdue_invoices'] + (int) $counts['services'];
        if ($people + $actions > 0)
        {
            $counts['sales_objective'] = $people + $actions;
        }

        $hooks = $this->campaignHooks($team, $weekStart);
        $details = [
            'projects' => $this->projectDetails($user, $team, $weekStart, $weekEnd),
            'draft_invoices' => $this->invoiceDetails($team, 'draft'),
            'overdue_invoices' => $this->invoiceDetails($team, 'overdue'),
            'services' => $this->serviceDetails($team),
            'new_leads' => $this->leadDetails($user, $team, $weekStart, $hooks),
            'list60' => $this->list60Details($user, $team, $weekEnd, $hooks),
            'list60_suggestions' => $this->suggestionDetails($user, $team, $weekStart, $hooks),
        ];
        $tax = $this->taxWindowForWeek($weekStart, $weekEnd);
        $items = [];

        foreach (self::ORDER as $key)
        {
            $count = (int) ($counts[$key] ?? 0);
            if ($count < 1)
            {
                continue;
            }

            if ($key === 'tax_filing' && $tax !== null)
            {
                $items[] = $this->scoped([
                    'key' => $key,
                    'count' => 1,
                    'label' => (string) __('app.weekly_plan_tax_filing', [
                        'quarter' => $tax['quarter_label'],
                        'deadline' => $tax['deadline_label'],
                    ]),
                    'href' => route('expense.index'),
                    'details' => [],
                    'quarter_label' => $tax['quarter_label'],
                    'deadline_label' => $tax['deadline_label'],
                ]);

                continue;
            }

            $label = $key === 'sales_objective'
                ? $this->salesObjectiveLabel($people, $actions)
                : trans_choice('app.weekly_plan_'.$key, $count, ['count' => $count]);

            $items[] = $this->scoped([
                'key' => $key,
                'count' => $count,
                'label' => $label,
                'href' => $this->href($key),
                'details' => $details[$key] ?? [],
            ]);
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $planned
     * @return list<array<string, mixed>>
     */
    private function buildReview(
        array $planned,
        User $user,
        Team $team,
        CarbonInterface $today,
        CarbonInterface $weekStart,
        CarbonInterface $weekEnd,
    ): array {
        $counts = $this->counts($user, $team, $weekStart, $weekEnd);
        $tax = $this->taxWindowForWeek($today->copy()->startOfDay(), $today->copy()->endOfDay());
        $review = [];

        foreach ($planned as $item)
        {
            $key = (string) ($item['key'] ?? '');
            $plannedCount = (int) ($item['count'] ?? 0);
            if ($key === '' || $key === 'sales_objective' || $plannedCount < 1)
            {
                continue;
            }

            if ($key === 'tax_filing')
            {
                $remaining = $tax !== null ? 1 : 0;
                $quarter = (string) ($item['quarter_label'] ?? '');
                $deadline = (string) ($item['deadline_label'] ?? '');
                $review[] = $this->scoped([
                    'key' => $key,
                    'count' => $remaining,
                    'planned' => $plannedCount,
                    'label' => $remaining > 0
                        ? (string) __('app.weekly_plan_tax_filing_open', ['quarter' => $quarter, 'deadline' => $deadline])
                        : (string) __('app.weekly_plan_tax_filing_done', ['quarter' => $quarter]),
                    'href' => $item['href'] ?? route('expense.index'),
                    'done' => $remaining === 0,
                ]);

                continue;
            }

            $remaining = (int) ($counts[$key] ?? 0);
            $task = (string) __('app.weekly_plan_task_'.$key);
            $review[] = $this->scoped([
                'key' => $key,
                'count' => $remaining,
                'planned' => $plannedCount,
                'label' => $remaining > 0
                    ? (string) __('app.weekly_plan_review_open', [
                        'task' => $task,
                        'remaining' => $remaining,
                        'planned' => $plannedCount,
                    ])
                    : (string) __('app.weekly_plan_review_cleared', ['task' => $task]),
                'href' => $item['href'] ?? $this->href($key),
                'done' => $remaining === 0,
            ]);
        }

        return $review;
    }

    /**
     * @return array<string, int>
     */
    private function counts(User $user, Team $team, CarbonInterface $weekStart, CarbonInterface $weekEnd): array
    {
        return [
            'company_email' => $this->unreadEmails($team, $team->teamMailboxes()->pluck('id')->all()),
            'personal_email' => $this->unreadEmails($team, $team->mailboxes()->forUser($user)->pluck('id')->all()),
            'whatsapp' => $this->unreadWhatsApp($team),
            'projects' => $this->projectsThisWeek($user, $team, $weekStart, $weekEnd),
            'draft_invoices' => $this->draftInvoices($team),
            'overdue_invoices' => $this->overdueInvoices($team),
            'services' => $this->servicesToChange($team),
            'tax_filing' => $this->taxWindowForWeek($weekStart, $weekEnd) !== null ? 1 : 0,
            'new_leads' => $this->newLeads($user, $team, $weekStart),
            'list60' => $this->list60Due($user, $team, $weekEnd),
            'list60_suggestions' => $this->campaignSuggestions($user, $team, $weekStart),
        ];
    }

    /**
     * @param  list<int>  $mailboxIds
     */
    private function unreadEmails(Team $team, array $mailboxIds): int
    {
        if (! $team->hasModule('mailbox') || $mailboxIds === [])
        {
            return 0;
        }

        return Email::query()
            ->where('team_id', $team->id)
            ->whereIn('mailbox_id', $mailboxIds)
            ->where('seen', false)
            ->count();
    }

    private function unreadWhatsApp(Team $team): int
    {
        if (! $team->hasModule('chat'))
        {
            return 0;
        }

        return app(DailyTeamDigestMetricsCollector::class)
            ->whatsappConversationQueryForTeam($team)
            ->where('direction', 'inbound')
            ->where('status', 'received')
            ->count();
    }

    private function projectsThisWeek(User $user, Team $team, CarbonInterface $weekStart, CarbonInterface $weekEnd): int
    {
        if (! $team->hasModule('projects'))
        {
            return 0;
        }

        return $this->projectsQuery($user, $team, $weekStart, $weekEnd)->count();
    }

    private function draftInvoices(Team $team): int
    {
        if (! $team->hasModule('invoices'))
        {
            return 0;
        }

        return $this->draftInvoicesQuery($team)->count();
    }

    private function overdueInvoices(Team $team): int
    {
        if (! $team->hasModule('invoices'))
        {
            return 0;
        }

        return $this->overdueInvoicesQuery($team)->count();
    }

    private function servicesToChange(Team $team): int
    {
        if (! $team->hasModule('services'))
        {
            return 0;
        }

        return $this->servicesQuery($team)->count();
    }

    private function newLeads(User $user, Team $team, CarbonInterface $today): int
    {
        $statusId = $this->contactStatusId('Lead');
        if ($statusId === null || ! $team->hasModule('contacts'))
        {
            return 0;
        }

        return $this->newLeadsQuery($user, $team, $today, $statusId)->count();
    }

    private function campaignSuggestions(User $user, Team $team, CarbonInterface $today): int
    {
        if (! $team->hasModule('contacts'))
        {
            return 0;
        }

        return $this->suggestionQuery($user, $team, $today, $this->campaignHooks($team, $today))->count();
    }

    /**
     * @param  array<int, array{campaign: string, summary: string}>  $hooks
     * @return list<array{label: string, href: string}>
     */
    private function projectDetails(User $user, Team $team, CarbonInterface $weekStart, CarbonInterface $weekEnd): array
    {
        if (! $team->hasModule('projects'))
        {
            return [];
        }

        $rows = $this->projectsQuery($user, $team, $weekStart, $weekEnd)
            ->orderBy('name')
            ->limit(self::DETAIL_LIMIT)
            ->get(['id', 'name']);

        return $this->capped($rows->map(fn (Project $project): array => [
            'label' => (string) $project->name,
            'href' => route('project.show', $project->id),
        ])->all(), $this->projectsThisWeek($user, $team, $weekStart, $weekEnd), route('project-list'));
    }

    /**
     * @return list<array{label: string, href: string}>
     */
    private function invoiceDetails(Team $team, string $filter): array
    {
        if (! $team->hasModule('invoices'))
        {
            return [];
        }

        $query = $filter === 'draft'
            ? $this->draftInvoicesQuery($team)
            : $this->overdueInvoicesQuery($team);

        $rows = (clone $query)->orderBy('number')->limit(self::DETAIL_LIMIT)->get(['id', 'number', 'enterprise_id']);
        $names = Enterprise::withoutGlobalScope('team')
            ->whereIn('id', $rows->pluck('enterprise_id')->filter()->all())
            ->pluck('name', 'id');
        $total = $filter === 'draft' ? $this->draftInvoices($team) : $this->overdueInvoices($team);
        $href = $this->href($filter === 'draft' ? 'draft_invoices' : 'overdue_invoices');

        return $this->capped($rows->map(function (Invoice $invoice) use ($names): array
        {
            $client = trim((string) ($names[$invoice->enterprise_id] ?? ''));
            $number = trim((string) ($invoice->number ?? ''));
            if ($number === '')
            {
                $number = '#'.$invoice->id;
            }

            return [
                'label' => $client !== '' ? $number.' · '.$client : $number,
                'href' => route('invoice.show', $invoice->id),
            ];
        })->all(), $total, $href);
    }

    /**
     * @return list<array{label: string, href: string}>
     */
    private function serviceDetails(Team $team): array
    {
        if (! $team->hasModule('services'))
        {
            return [];
        }

        $rows = $this->servicesQuery($team)
            ->orderBy('description')
            ->limit(self::DETAIL_LIMIT)
            ->get(['id', 'description', 'enterprise_id', 'status']);
        $names = Enterprise::withoutGlobalScope('team')
            ->whereIn('id', $rows->pluck('enterprise_id')->filter()->all())
            ->pluck('name', 'id');

        return $this->capped($rows->map(function (Service $service) use ($names): array
        {
            $action = (int) $service->status === 3
                ? (string) __('app.weekly_plan_service_activate')
                : (string) __('app.weekly_plan_service_suspend');
            $client = trim((string) ($names[$service->enterprise_id] ?? ''));
            $description = trim((string) $service->description);
            if ($description === '')
            {
                $description = '#'.$service->id;
            }

            return [
                'label' => $client !== '' ? $action.' · '.$description.' · '.$client : $action.' · '.$description,
                'href' => route('service.show', $service->id),
            ];
        })->all(), $this->servicesToChange($team), route('service-list'));
    }

    /**
     * @param  array<int, array{campaign: string, summary: string}>  $hooks
     * @return list<array{label: string, href: string, note: string}>
     */
    private function leadDetails(User $user, Team $team, CarbonInterface $today, array $hooks): array
    {
        $statusId = $this->contactStatusId('Lead');
        if ($statusId === null || ! $team->hasModule('contacts'))
        {
            return [];
        }

        $rows = $this->newLeadsQuery($user, $team, $today, $statusId)
            ->orderBy('name')
            ->limit(self::DETAIL_LIMIT)
            ->get(['id', 'name', 'surname']);

        return $this->capped($rows->map(fn (Contact $contact): array => [
            'label' => $this->contactLabel($contact),
            'href' => route('contact.show', $contact->id),
            'note' => $this->dialogue($contact, $hooks[(int) $contact->id] ?? null),
        ])->all(), $this->newLeads($user, $team, $today), route('contact-list'));
    }

    /**
     * @param  array<int, array{campaign: string, summary: string}>  $hooks
     * @return list<array{label: string, href: string, note: string}>
     */
    private function list60Details(User $user, Team $team, CarbonInterface $weekEnd, array $hooks): array
    {
        if (! $team->hasModule('list60'))
        {
            return [];
        }

        $rows = $this->list60Query($user, $team, $weekEnd)
            ->with(['contact' => function ($query): void
            {
                $query->withoutGlobalScopes();
            }])
            ->limit(self::DETAIL_LIMIT)
            ->get();

        $details = [];
        foreach ($rows as $row)
        {
            $contact = $row->contact;
            if (! $contact instanceof Contact)
            {
                continue;
            }

            $details[] = [
                'label' => $this->contactLabel($contact),
                'href' => route('contact.show', $contact->id),
                'note' => $this->dialogue($contact, $hooks[(int) $contact->id] ?? null),
            ];
        }

        return $this->capped($details, $this->list60Due($user, $team, $weekEnd), route('list60-list'));
    }

    /**
     * @param  array<int, array{campaign: string, summary: string}>  $hooks
     * @return list<array{label: string, href: string, note: string}>
     */
    private function suggestionDetails(User $user, Team $team, CarbonInterface $today, array $hooks): array
    {
        if (! $team->hasModule('contacts'))
        {
            return [];
        }

        $rows = $this->suggestionQuery($user, $team, $today, $hooks)
            ->orderBy('name')
            ->limit(self::DETAIL_LIMIT)
            ->get(['id', 'name', 'surname']);

        return $this->capped($rows->map(fn (Contact $contact): array => [
            'label' => $this->contactLabel($contact),
            'href' => route('contact.show', $contact->id),
            'note' => $this->dialogue($contact, $hooks[(int) $contact->id] ?? null),
        ])->all(), $this->campaignSuggestions($user, $team, $today), route('list60-list'));
    }

    /**
     * @param  array{campaign: string, summary: string}|null  $hook
     */
    private function dialogue(Contact $contact, ?array $hook): string
    {
        $name = Str::of($this->contactLabel($contact))->before(' ')->trim()->toString();
        if ($name === '')
        {
            $name = $this->contactLabel($contact);
        }

        $campaign = trim((string) ($hook['campaign'] ?? ''));
        if ($campaign !== '')
        {
            $summary = trim((string) ($hook['summary'] ?? ''));
            $hookText = $summary !== ''
                ? Str::limit($summary, 120, '…')
                : (string) __('app.weekly_plan_dialogue_hook');

            return (string) __('app.weekly_plan_dialogue_campaign', [
                'name' => $name,
                'campaign' => $campaign,
                'hook' => $hookText,
            ]);
        }

        return (string) __('app.weekly_plan_dialogue_default', ['name' => $name]);
    }

    private function contactLabel(Contact $contact): string
    {
        $name = trim($contact->name.' '.(string) ($contact->surname ?? ''));

        return $name !== '' ? $name : (string) __('app.weekly_plan_unnamed_contact');
    }

    /**
     * @param  list<array<string, mixed>>  $details
     * @return list<array<string, mixed>>
     */
    private function capped(array $details, int $total, string $href): array
    {
        $rest = $total - count($details);
        if ($rest > 0)
        {
            $details[] = [
                'label' => (string) __('app.weekly_plan_more', ['count' => $rest]),
                'href' => $href,
            ];
        }

        return $details;
    }

    private function salesObjectiveLabel(int $people, int $actions): string
    {
        if ($people > 0 && $actions > 0)
        {
            return (string) __('app.weekly_plan_sales_objective', [
                'people' => $people,
                'actions' => $actions,
            ]);
        }

        if ($people > 0)
        {
            return trans_choice('app.weekly_plan_sales_people', $people, ['count' => $people]);
        }

        return trans_choice('app.weekly_plan_sales_actions', $actions, ['count' => $actions]);
    }

    /**
     * @param  array<int, array{campaign: string, summary: string}>  $hooks
     */
    private function suggestionQuery(User $user, Team $team, CarbonInterface $today, array $hooks): Builder
    {
        $statusIds = ContactStatus::query()
            ->whereIn('name', ['Lead', 'En seguimiento'])
            ->pluck('id')
            ->all();
        $contactIds = array_keys(array_filter($hooks, fn (array $hook): bool => ($hook['campaign'] ?? '') !== ''));
        $leadStatusId = $this->contactStatusId('Lead');
        $alreadyListed = $leadStatusId === null
            ? []
            : $this->newLeadsQuery($user, $team, $today, $leadStatusId)->pluck('id')->all();

        return Contact::withoutGlobalScope('team')
            ->withoutGlobalScope('ownership')
            ->where('team_id', $team->id)
            ->whereIn('status_id', $statusIds !== [] ? $statusIds : [0])
            ->whereIn('id', $contactIds !== [] ? $contactIds : [0])
            ->when($alreadyListed !== [], fn (Builder $query) => $query->whereNotIn('id', $alreadyListed))
            ->whereDoesntHave('list60');
    }

    private function newLeadsQuery(User $user, Team $team, CarbonInterface $today, int $statusId): Builder
    {
        return Contact::withoutGlobalScope('team')
            ->withoutGlobalScope('ownership')
            ->where('team_id', $team->id)
            ->where('responsible_id', $user->id)
            ->where('status_id', $statusId)
            ->where('created_at', '>=', Carbon::parse($today)->copy()->subDays(30)->startOfDay())
            ->whereDoesntHave('list60');
    }

    private function servicesQuery(Team $team): Builder
    {
        $enterpriseIds = Enterprise::withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->pluck('id');

        return Service::withoutGlobalScope('team')
            ->withoutGlobalScope('ownership')
            ->whereIn('enterprise_id', $enterpriseIds->all() !== [] ? $enterpriseIds->all() : [0])
            ->whereIn('status', [2, 3]);
    }

    private function projectsQuery(User $user, Team $team, CarbonInterface $weekStart, CarbonInterface $weekEnd): Builder
    {
        return Project::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('responsible_id', $user->id)
            ->whereIn('status_id', ProjectStatus::ongoingDashboardStatusIds())
            ->whereNotNull('date_end')
            ->whereDate('date_end', '>=', $weekStart->toDateString())
            ->whereDate('date_end', '<=', $weekEnd->toDateString());
    }

    private function draftInvoicesQuery(Team $team): Builder
    {
        return Invoice::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('operation', 'sell')
            ->where('status', InvoiceSummaryService::DRAFT_STATUS);
    }

    private function overdueInvoicesQuery(Team $team): Builder
    {
        return app(InvoiceSummaryService::class)
            ->applySummaryFilter(
                Invoice::withoutGlobalScopes()->where('team_id', $team->id),
                'overdue',
            );
    }

    private function list60Query(User $user, Team $team, CarbonInterface $weekEnd): Builder
    {
        return List60::query()
            ->where('responsible_id', $user->id)
            ->whereDate('date_next', '<=', $weekEnd->toDateString())
            ->whereHas('contact', function ($query) use ($team): void
            {
                $query->withoutGlobalScopes()->where('team_id', $team->id);
            });
    }

    /**
     * Latest opened or clicked Mailer campaign per contact, from the last 45 days.
     *
     * @return array<int, array{campaign: string, summary: string}>
     */
    private function campaignHooks(Team $team, CarbonInterface $today): array
    {
        $rows = MessageDelivery::query()
            ->where('team_id', $team->id)
            ->whereNotNull('contact_id')
            ->where('sent_at', '>=', Carbon::parse($today)->copy()->subDays(45))
            ->with([
                'campaign:id,name,summary',
                'message:id,name,text',
            ])
            ->orderByRaw('clicked_at is null')
            ->orderByRaw('opened_at is null')
            ->orderByDesc('sent_at')
            ->limit(800)
            ->get();

        $hooks = [];
        foreach ($rows as $row)
        {
            $contactId = (int) $row->contact_id;
            if (isset($hooks[$contactId]))
            {
                continue;
            }

            $campaign = trim((string) ($row->campaign->name ?? ''));
            $summary = trim((string) ($row->campaign->summary ?? ''));
            if ($campaign === '')
            {
                $campaign = trim((string) ($row->message->name ?? ''));
            }
            if ($summary === '')
            {
                $summary = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($row->message->text ?? ''))) ?? '');
            }

            $hooks[$contactId] = [
                'campaign' => $campaign,
                'summary' => $summary,
            ];
        }

        return $hooks;
    }

    private function contactStatusId(string $name): ?int
    {
        $id = ContactStatus::query()->where('name', $name)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @param  list<array<string, mixed>>|null  $items
     */
    private function itemsLackBreakdown(?array $items): bool
    {
        if ($items === null || $items === [])
        {
            return false;
        }

        foreach ($items as $item)
        {
            if (! is_array($item) || ! array_key_exists('details', $item) || (int) ($item['shape'] ?? 0) < 3)
            {
                return true;
            }
        }

        return false;
    }

    private function list60Due(User $user, Team $team, CarbonInterface $weekEnd): int
    {
        if (! $team->hasModule('list60'))
        {
            return 0;
        }

        return $this->list60Query($user, $team, $weekEnd)->count();
    }

    /**
     * Filing months are January (Q4, until the 30th), April, July and October (until the 20th).
     *
     * @return array{quarter_label: string, deadline_label: string}|null
     */
    private function taxWindowForWeek(CarbonInterface $weekStart, CarbonInterface $weekEnd): ?array
    {
        $cursor = Carbon::parse($weekStart)->startOfDay();
        $end = Carbon::parse($weekEnd)->copy()->addDays(7)->startOfDay();

        while ($cursor->lte($end))
        {
            $window = $this->filingMonth($cursor);
            if ($window !== null)
            {
                return $window;
            }

            $cursor->addDay();
        }

        return null;
    }

    /**
     * @return array{quarter_label: string, deadline_label: string}|null
     */
    private function filingMonth(CarbonInterface $day): ?array
    {
        $month = (int) $day->month;
        if (! in_array($month, [1, 4, 7, 10], true))
        {
            return null;
        }

        $deadlineDay = $month === 1 ? 30 : 20;

        [$quarter, $year] = match ($month)
        {
            1 => [4, (int) $day->year - 1],
            4 => [1, (int) $day->year],
            7 => [2, (int) $day->year],
            default => [3, (int) $day->year],
        };

        $months = [
            1 => 'enero',
            4 => 'abril',
            7 => 'julio',
            10 => 'octubre',
        ];

        return [
            'quarter_label' => 'T'.$quarter.' '.$year,
            'deadline_label' => $deadlineDay.' de '.$months[$month],
        ];
    }

    private function monthlyChallenge(Team $team): ?string
    {
        $saved = $team->getSetting('business_config', []);
        if (is_string($saved))
        {
            $saved = json_decode($saved, true) ?: [];
        }

        if (! is_array($saved))
        {
            return null;
        }

        $challenge = trim((string) ($saved['business_challenge'] ?? ''));
        if ($challenge === '')
        {
            return null;
        }

        if (mb_strlen($challenge) > 160)
        {
            return mb_substr($challenge, 0, 157).'…';
        }

        return $challenge;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function scoped(array $item): array
    {
        $item['shape'] = 3;
        $item['scope'] = in_array($item['key'] ?? '', [
            'personal_email',
            'projects',
            'new_leads',
            'list60',
        ], true)
            ? 'user'
            : 'team';

        return $item;
    }

    private function href(string $key): string
    {
        return match ($key)
        {
            'company_email', 'personal_email' => route('mail-list'),
            'whatsapp' => route('chat.index'),
            'projects' => route('project-list'),
            'draft_invoices' => route('invoice.index', ['summary_filter' => 'draft']),
            'overdue_invoices' => route('invoice.index', ['summary_filter' => 'overdue']),
            'services' => route('service-list'),
            'tax_filing' => route('expense.index'),
            'new_leads' => route('contact-list'),
            'list60', 'list60_suggestions' => route('list60-list'),
            'sales_objective' => route('weekly-plan.index'),
            default => route('dashboard'),
        };
    }
}
