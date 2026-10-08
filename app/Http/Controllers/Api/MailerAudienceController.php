<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContactInteractionType;
use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMailerAudienceContactRequest;
use App\Http\Requests\Api\StoreMailerAudienceListRequest;
use App\Http\Requests\Api\UpdateMailerAudienceContactRequest;
use App\Http\Requests\StoreContactInteractionRequest;
use App\Jobs\DispatchAudienceEmailDomainChecks;
use App\Jobs\ValidateAudienceEmailDomainsJob;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactInteraction;
use App\Models\ContactStatus;
use App\Models\List60;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\MessageDeliveryLink;
use App\Models\Module;
use App\Services\MailerCategoryService;
use App\Support\AssignableTeamUsers;
use App\Support\List60NextContactDate;
use App\Support\List60StatusAdvancer;
use App\Support\SearchNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MailerAudienceController extends Controller
{
    use ChecksTeamModule;

    public function validateDomains(Request $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        $teamId = (int) $team->id;
        if (! ValidateAudienceEmailDomainsJob::isRunning($teamId))
        {
            $token = (string) Str::uuid();
            ValidateAudienceEmailDomainsJob::markRunning(
                $teamId,
                $token,
                ValidateAudienceEmailDomainsJob::countContacts($teamId),
            );
            $state = Cache::get(ValidateAudienceEmailDomainsJob::cacheKey($teamId));
            $startedAt = is_array($state) ? (string) ($state['started_at'] ?? '') : '';
            DispatchAudienceEmailDomainChecks::dispatch($teamId, $token, $startedAt)->afterResponse();
        }

        return response()->json([
            'success' => true,
            'message' => 'Estamos revisando los dominios. Los que no tengan MX quedan fuera del envío.',
            ...ValidateAudienceEmailDomainsJob::progress($teamId),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer',
            'status_id' => 'nullable|integer',
            'issue' => 'nullable|string|in:validated,unchecked,error,failed',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = $this->audienceQuery((int) $team->id);

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '')
        {
            SearchNormalizer::applyContactNavbarConditions($query, $search);
        }

        $categoryId = (int) ($validated['category_id'] ?? 0);
        if ($categoryId > 0)
        {
            $query->whereHas('categories', function (Builder $builder) use ($categoryId): void
            {
                $builder->where('categories.id', $categoryId);
            });
        }

        $statusId = (int) ($validated['status_id'] ?? 0);
        if ($statusId > 0)
        {
            $query->where('status_id', $statusId);
        }

        $this->applyEmailIssueFilter($query, (string) ($validated['issue'] ?? ''));

        $paginator = $query
            ->orderBy('name')
            ->orderBy('surname')
            ->paginate((int) ($validated['per_page'] ?? 20), ['*'], 'page', (int) ($validated['page'] ?? 1));

        $paginator->setPath($request->url());
        $paginator->appends($request->query());

        return response()->json([
            'success' => true,
            'data' => $paginator->getCollection()
                ->map(fn (Contact $contact): array => $this->formatContact($contact))
                ->values()
                ->all(),
            'lists' => $this->listsForTeam((int) $team->id),
            'status_stats' => $this->statusStats((int) $team->id),
            'usage' => $team->getMailerUsageSummary(),
            'domain_check' => ValidateAudienceEmailDomainsJob::progress((int) $team->id),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreMailerAudienceContactRequest $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        $validated = $request->validated();
        $ownerId = (int) $request->user()->id;
        $leadStatusId = (int) ($validated['status_id'] ?? ContactStatus::query()->where('name', 'Lead')->value('id') ?? 1);

        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => trim((string) $validated['name']),
            'surname' => trim((string) ($validated['surname'] ?? '')) ?: null,
            'email' => Str::lower(trim((string) $validated['email'])),
            'phone' => $this->nullablePhone($validated['phone'] ?? null),
            'language' => $this->defaultLanguageCode(),
            'country' => $this->defaultCountryId(),
            'creator_id' => $ownerId,
            'responsible_id' => $ownerId,
            'status_id' => $leadStatusId,
        ]);

        $categoryIds = Category::onlyExistingIds($validated['category_ids'] ?? []);
        if ($categoryIds !== [])
        {
            $contact->categories()->sync($categoryIds);
        }

        $contact->load(['status', 'categories', 'user']);

        return response()->json([
            'success' => true,
            'data' => $this->formatContact($contact),
        ], 201);
    }

    public function update(UpdateMailerAudienceContactRequest $request, int $id): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        $contact = $this->contactForTeam((int) $team->id, $id);
        if ($contact instanceof JsonResponse)
        {
            return $contact;
        }

        $validated = $request->validated();
        $contact->fill([
            'name' => trim((string) $validated['name']),
            'surname' => trim((string) ($validated['surname'] ?? '')) ?: null,
            'email' => filled($validated['email'] ?? null) ? Str::lower(trim((string) $validated['email'])) : null,
        ]);

        if (array_key_exists('phone', $validated))
        {
            $contact->phone = $this->nullablePhone($validated['phone']);
        }

        if (array_key_exists('status_id', $validated) && $validated['status_id'] !== null)
        {
            $contact->status_id = (int) $validated['status_id'];
        }

        $contact->save();
        $contact->categories()->sync(Category::onlyExistingIds($validated['category_ids'] ?? []));
        $contact->load(['status', 'categories', 'user']);

        return response()->json([
            'success' => true,
            'data' => $this->formatContact($contact),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        $contact = $this->contactForTeam((int) $team->id, $id);
        if ($contact instanceof JsonResponse)
        {
            return $contact;
        }

        $contact->load(['status', 'categories', 'user']);

        return response()->json([
            'success' => true,
            'data' => $this->formatContact($contact),
            'lists' => $this->listsForTeam((int) $team->id),
        ]);
    }

    public function storeInteraction(StoreContactInteractionRequest $request, int $id): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        $contact = $this->contactForTeam((int) $team->id, $id);
        if ($contact instanceof JsonResponse)
        {
            return $contact;
        }

        $user = $request->user();
        if (! $user || ! $user->can('logInteraction', $contact))
        {
            return response()->json([
                'success' => false,
                'message' => __('No podés registrar interacciones en este contacto.'),
            ], 403);
        }

        $validated = $request->validated();
        unset($validated['opportunity_id']);

        $interaction = new ContactInteraction($validated);
        $interaction->contact_id = $contact->id;
        $interaction->user_id = $user->id;
        $interaction->save();

        $type = $interaction->type instanceof ContactInteractionType
            ? $interaction->type
            : ContactInteractionType::from((string) $interaction->type);

        return response()->json([
            'success' => true,
            'message' => __('Interaction recorded.'),
            'data' => [
                'id' => (int) $interaction->id,
                'type' => $type->value,
                'type_label' => $type->label(),
                'subject' => $interaction->subject,
                'body' => $interaction->body,
                'occurred_at' => $interaction->occurred_at?->format('Y-m-d H:i'),
            ],
        ], 201);
    }

    public function indexList60(Request $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        if (! $team->hasModule('list60'))
        {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => [
                    'enabled' => false,
                    'count' => 0,
                    'limit' => 60,
                ],
            ]);
        }

        $user = $request->user();
        $query = List60::query()
            ->with([
                'contact' => function ($builder): void
                {
                    $builder->withoutGlobalScopes()->with(['status', 'categories', 'user']);
                },
                'status:id,name',
                'responsible:id,name',
            ])
            ->whereHas('contact', function (Builder $builder) use ($team): void
            {
                $builder->withoutGlobalScopes()->where('team_id', $team->id);
            });

        if ($user && ! $user->hasRole('admin'))
        {
            $query->where('responsible_id', $user->id);
        }

        $rows = $query
            ->orderBy('date_next')
            ->orderBy('id')
            ->get()
            ->filter(fn (List60 $entry): bool => $entry->contact !== null)
            ->map(fn (List60 $entry): array => $this->formatList60Entry($entry))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'enabled' => true,
                'count' => $rows->count(),
                'limit' => 60,
            ],
        ]);
    }

    public function storeList60(Request $request, int $id): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        if (! $team->hasModule('list60'))
        {
            return response()->json([
                'success' => false,
                'message' => 'Este equipo no tiene la Lista 60.',
            ], 403);
        }

        $contact = $this->contactForTeam((int) $team->id, $id);
        if ($contact instanceof JsonResponse)
        {
            return $contact;
        }

        $note = $this->newsFollowUpNote($team->id, $contact, $request->input('delivery_id'));

        $existing = List60::query()->where('contact_id', $contact->id)->first();
        if ($existing)
        {
            if ($note !== null)
            {
                $this->appendList60Note($contact, $existing, $note);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'contact_id' => (int) $contact->id,
                    'in_list60' => true,
                    'already' => true,
                ],
            ]);
        }

        $user = $request->user();
        $responsible = AssignableTeamUsers::forTeam($team)->firstWhere('id', (int) $user->id);
        if (! $responsible)
        {
            return response()->json([
                'success' => false,
                'message' => 'No hay un responsable válido para la Lista 60.',
            ], 422);
        }

        $totalContacts = List60::query()
            ->join('contacts', 'list60.contact_id', '=', 'contacts.id')
            ->where('contacts.team_id', $team->id)
            ->where('list60.responsible_id', $responsible->id)
            ->count();
        if ($totalContacts >= 60)
        {
            return response()->json([
                'success' => false,
                'message' => 'La lista ya tiene 60 contactos.',
            ], 422);
        }

        $record = new List60;
        $record->contact_id = $contact->id;
        $record->date_next = List60NextContactDate::afterOutreach();
        $record->responsible_id = $responsible->id;
        $record->status_id = List60StatusAdvancer::initialStatusId();
        $record->notes = $note;
        $record->save();

        if ($note !== null)
        {
            $this->appendContactNote($contact, $note);
        }

        $followingStatus = ContactStatus::query()->where('name', 'En seguimiento')->first();
        if ($followingStatus)
        {
            $contact->update(['status_id' => $followingStatus->id]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'contact_id' => (int) $contact->id,
                'in_list60' => true,
                'already' => false,
            ],
        ], 201);
    }

    public function storeList(StoreMailerAudienceListRequest $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        if ($denied = $this->ensureTeamModule($team, 'contacts'))
        {
            return $denied;
        }

        $name = trim((string) $request->validated('name'));
        $category = $this->findOrCreateList((int) $team->id, $name);

        if ($category instanceof JsonResponse)
        {
            return $category;
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatList($category),
        ], $category->wasRecentlyCreated ? 201 : 200);
    }

    private function newsFollowUpNote(int $teamId, Contact $contact, mixed $deliveryId): ?string
    {
        $deliveryId = (int) $deliveryId;
        if ($deliveryId <= 0)
        {
            return null;
        }

        $delivery = MessageDelivery::query()
            ->with('message')
            ->where('id', $deliveryId)
            ->where('contact_id', $contact->id)
            ->whereHas('message', function (Builder $query) use ($teamId): void
            {
                $query->where('team_id', $teamId);
            })
            ->first();

        if (! $delivery)
        {
            return null;
        }

        $name = trim((string) ($delivery->message?->name ?? ''));
        $title = $name !== '' ? '«'.$name.'»' : 'sin título';
        $sent = $delivery->sent_at
            ? 'Se envió el '.$this->followUpMoment($delivery->sent_at).'.'
            : 'Se envió.';
        $opened = $delivery->opened_at
            ? 'Lo abrió el '.$this->followUpMoment($delivery->opened_at).'.'
            : 'No lo abrió.';
        $clicked = 'No hizo clic.';
        if ($delivery->clicked_at)
        {
            $clicked = 'Hizo clic el '.$this->followUpMoment($delivery->clicked_at);
            $links = $this->clickedLinks($delivery);
            $clicked .= $links === [] ? '.' : ' en '.$this->joinClickedLinks($links).'.';
        }

        return "News {$title}. {$sent} {$opened} {$clicked}";
    }

    /**
     * @return list<string>
     */
    private function clickedLinks(MessageDelivery $delivery): array
    {
        return MessageDeliveryLink::query()
            ->where('message_delivery_id', $delivery->id)
            ->where('click_count', '>', 0)
            ->orderBy('id')
            ->pluck('link')
            ->map(fn (mixed $link): string => trim((string) $link))
            ->filter(fn (string $link): bool => $link !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $links
     */
    private function joinClickedLinks(array $links): string
    {
        if (count($links) < 2)
        {
            return $links[0] ?? '';
        }

        $last = array_pop($links);

        return implode(', ', $links).' y '.$last;
    }

    private function followUpMoment(\DateTimeInterface $moment): string
    {
        return Carbon::parse($moment)
            ->timezone((string) config('app.timezone'))
            ->format('d/m/Y H:i');
    }

    private function appendList60Note(Contact $contact, List60 $record, string $note): void
    {
        $record->notes = $this->joinFollowUpNotes(trim((string) $record->notes), $note);
        $record->save();
        $this->appendContactNote($contact, $note);
    }

    private function appendContactNote(Contact $contact, string $note): void
    {
        $data = (array) ($contact->data ?? []);
        $data['notes'] = $this->joinFollowUpNotes(trim((string) ($data['notes'] ?? '')), $note);
        $contact->update(['data' => $data]);
    }

    private function joinFollowUpNotes(string $current, string $note): string
    {
        if ($current === '')
        {
            return $note;
        }

        if (str_contains($current, $note))
        {
            return $current;
        }

        return $current."\n".$note;
    }

    private function applyEmailIssueFilter(Builder $query, string $issue): void
    {
        if ($issue === 'failed')
        {
            $query->where('data->channels->email->valid', false);

            return;
        }

        if ($issue === 'validated')
        {
            $query->where('data->channels->email->domain', 'ok')
                ->where(function (Builder $channel): void
                {
                    $channel->whereNull('data->channels->email->valid')
                        ->orWhere('data->channels->email->valid', true);
                });

            return;
        }

        if ($issue === 'unchecked')
        {
            $query->where(function (Builder $channel): void
            {
                $channel->whereNull('data->channels->email->domain')
                    ->orWhere('data->channels->email->domain', '');
            })->where(function (Builder $channel): void
            {
                $channel->whereNull('data->channels->email->valid')
                    ->orWhere('data->channels->email->valid', true);
            });

            return;
        }

        if ($issue !== 'error')
        {
            return;
        }

        $query->where(function (Builder $channel): void
        {
            $channel->whereNull('data->channels->email->valid')
                ->orWhere('data->channels->email->valid', true);
        });

        $column = $query->getGrammar()->wrap('data');
        $driver = $query->getConnection()->getDriverName();
        if ($driver === 'pgsql')
        {
            $errorText = "LOWER(COALESCE({$column} #>> '{channels,email,last_error,message}', ''))";
            $summaryText = "LOWER(COALESCE({$column} #>> '{last_message,summary}', ''))";
        } else
        {
            $errorText = "LOWER(COALESCE(json_extract({$column}, '$.\"channels\".\"email\".\"last_error\".\"message\"'), ''))";
            $summaryText = "LOWER(COALESCE(json_extract({$column}, '$.\"last_message\".\"summary\"'), ''))";
        }

        $likes = [];
        foreach (MessageDelivery::temporaryFailureNeedles() as $needle)
        {
            $safe = str_replace("'", "''", mb_strtolower($needle));
            $likes[] = "{$errorText} LIKE '%{$safe}%'";
            $likes[] = "{$summaryText} LIKE '%{$safe}%'";
        }

        $query->whereRaw('('.implode(' OR ', $likes).')');
    }

    private function audienceQuery(int $teamId): Builder
    {
        return Contact::query()
            ->with(['status', 'categories', 'user'])
            ->where('team_id', $teamId)
            ->whereNotNull('email')
            ->where('email', '!=', '');
    }

    private function contactForTeam(int $teamId, int $id): Contact|JsonResponse
    {
        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereKey($id)
            ->first();

        if (! $contact)
        {
            return response()->json([
                'success' => false,
                'message' => __('No encontramos ese contacto.'),
            ], 404);
        }

        return $contact;
    }

    /**
     * @return list<array{id: int, name: string, color: string|null, subscribers: int}>
     */
    private function listsForTeam(int $teamId): array
    {
        $contactsModuleId = Module::query()->where('key', 'contacts')->value('id');

        return Category::query()
            ->where('team_id', $teamId)
            ->when($contactsModuleId, fn (Builder $query) => $query->where('module_id', $contactsModuleId))
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => $this->formatList($category))
            ->values()
            ->all();
    }

    private function findOrCreateList(int $teamId, string $name): Category|JsonResponse
    {
        $moduleId = Module::query()->where('key', 'contacts')->value('id');
        if (! $moduleId)
        {
            return response()->json([
                'success' => false,
                'message' => __('El módulo de contactos no está disponible.'),
            ], 422);
        }

        $normalized = mb_strtolower($name);
        $existing = Category::query()
            ->where('team_id', $teamId)
            ->where('module_id', $moduleId)
            ->whereNull('deleted_at')
            ->get()
            ->first(fn (Category $category): bool => mb_strtolower(trim((string) $category->name)) === $normalized);

        if ($existing)
        {
            return $existing;
        }

        return Category::query()->create([
            'name' => $name,
            'module_id' => $moduleId,
            'team_id' => $teamId,
            'parent_id' => null,
            'order' => 0,
            'status' => 1,
        ]);
    }

    /**
     * Same four pipeline cards as the cms8 contact list (Leads, En seguimiento, Clientes, Finalizados).
     *
     * @return list<array{key: string, status_id: int, label: string, hint: string, count: int, percentage: float, label_class: string}>
     */
    private function statusStats(int $teamId): array
    {
        $cards = [
            [
                'key' => 'leads',
                'name' => 'Lead',
                'label' => 'Leads',
                'hint' => 'Total de leads',
                'label_class' => 'bg-label-success',
                'fallback_id' => 1,
            ],
            [
                'key' => 'follow_up',
                'name' => 'En seguimiento',
                'label' => 'En seguimiento',
                'hint' => 'Total en seguimiento',
                'label_class' => 'bg-label-warning',
                'fallback_id' => 2,
            ],
            [
                'key' => 'clients',
                'name' => 'Cliente',
                'label' => 'Clientes',
                'hint' => 'Total de clientes',
                'label_class' => 'bg-label-primary',
                'fallback_id' => 5,
            ],
            [
                'key' => 'finished',
                'name' => 'Finalizado',
                'label' => 'Finalizados',
                'hint' => 'Total finalizados',
                'label_class' => 'bg-label-dark',
                'fallback_id' => 6,
            ],
        ];

        $statuses = ContactStatus::query()->get()->keyBy('name');
        $counts = Contact::query()
            ->where('team_id', $teamId)
            ->selectRaw('status_id, count(*) as aggregate')
            ->groupBy('status_id')
            ->pluck('aggregate', 'status_id');

        $trackedIds = collect($cards)->map(function (array $card) use ($statuses): int
        {
            $status = $statuses->get($card['name']);

            return $status ? (int) $status->id : (int) $card['fallback_id'];
        });

        $total = (int) $counts->only($trackedIds->all())->sum();

        return collect($cards)
            ->map(function (array $card) use ($statuses, $counts, $total): array
            {
                $status = $statuses->get($card['name']);
                $statusId = $status ? (int) $status->id : (int) $card['fallback_id'];
                $count = (int) ($counts[$statusId] ?? 0);

                return [
                    'key' => $card['key'],
                    'status_id' => $statusId,
                    'label' => $card['label'],
                    'hint' => $card['hint'],
                    'count' => $count,
                    'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0,
                    'label_class' => (string) ($status?->label_class ?? $card['label_class']),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatList60Entry(List60 $entry): array
    {
        $notes = trim((string) ($entry->notes ?? ''));

        return [
            'id' => (int) $entry->id,
            'contact' => $this->formatContact($entry->contact),
            'status' => $entry->status?->name,
            'date_next' => $entry->date_next?->toDateString(),
            'notes' => $notes !== '' ? $notes : null,
            'responsible' => $entry->responsible?->name,
        ];
    }

    /**
     * @return array{id: int, name: string, color: string|null, subscribers: int}
     */
    private function formatList(Category $category): array
    {
        return [
            'id' => (int) $category->id,
            'name' => (string) $category->name,
            'color' => MailerCategoryService::normalizeColor($category->color),
            'subscribers' => $category->contacts()
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatContact(Contact $contact): array
    {
        $email = (string) $contact->email;
        $display = trim($contact->name.' '.($contact->surname ?? ''));

        return [
            'id' => (int) $contact->id,
            'name' => (string) $contact->name,
            'surname' => $contact->surname,
            'display_name' => $display !== '' ? $display : $email,
            'email' => $email,
            'phone' => $contact->phone ? (string) $contact->phone : null,
            'status' => $contact->status
                ? [
                    'id' => (int) $contact->status->id,
                    'name' => (string) $contact->status->name,
                    'label_class' => (string) ($contact->status->label_class ?? 'bg-label-secondary'),
                ]
                : null,
            'categories' => $contact->categories
                ->map(fn (Category $category): array => [
                    'id' => (int) $category->id,
                    'name' => (string) $category->name,
                    'color' => MailerCategoryService::normalizeColor($category->color),
                ])
                ->values()
                ->all(),
            'can_send' => $this->canSendToEmail($email),
            'photo_url' => $this->photoUrl($contact),
            'email_valid' => $contact->storedChannelValid('email'),
            'email_domain_ok' => $contact->emailDomainOk(),
            'whatsapp_valid' => $contact->storedChannelValid('whatsapp'),
            'email_last_error' => $contact->storedChannelLastError('email'),
            'whatsapp_last_error' => $contact->storedChannelLastError('whatsapp'),
            'last_message' => $contact->lastOutboundMessage(),
        ];
    }

    private function photoUrl(Contact $contact): ?string
    {
        return $contact->storedPhotoUrl();
    }

    private function nullablePhone(mixed $phone): ?string
    {
        $value = trim((string) ($phone ?? ''));

        return $value !== '' ? $value : null;
    }

    private function canSendToEmail(string $email): bool
    {
        $haystack = Str::lower($email);

        foreach (Message::demoEmailDomainsExcludedFromAudience() as $domain)
        {
            if (str_ends_with($haystack, Str::lower($domain)))
            {
                return false;
            }
        }

        return $email !== '';
    }

    private function defaultCountryId(): int
    {
        return (int) (DB::table('countries')->where('id', 724)->value('id')
            ?? DB::table('countries')->value('id')
            ?? 724);
    }

    private function defaultLanguageCode(): string
    {
        return (string) (DB::table('languages')->where('code', 'es')->value('code')
            ?? DB::table('languages')->value('code')
            ?? 'es');
    }
}
