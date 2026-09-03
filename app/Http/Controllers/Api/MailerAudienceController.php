<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMailerAudienceContactRequest;
use App\Http\Requests\Api\StoreMailerAudienceListRequest;
use App\Http\Requests\Api\UpdateMailerAudienceContactRequest;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Message;
use App\Models\Module;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MailerAudienceController extends Controller
{
    use ChecksTeamModule;

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
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = $this->audienceQuery((int) $team->id);

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '')
        {
            $term = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($term): void
            {
                $builder->where('name', 'like', $term)
                    ->orWhere('surname', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
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
            'email' => Str::lower(trim((string) $validated['email'])),
        ]);

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
     * @return list<array{id: int, name: string, subscribers: int}>
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
     * @return array{id: int, name: string, subscribers: int}
     */
    private function formatList(Category $category): array
    {
        return [
            'id' => (int) $category->id,
            'name' => (string) $category->name,
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
                ])
                ->values()
                ->all(),
            'can_send' => $this->canSendToEmail($email),
            'photo_url' => $this->photoUrl($contact),
        ];
    }

    private function photoUrl(Contact $contact): ?string
    {
        $userPhoto = $contact->user?->profile_photo_url;
        if (is_string($userPhoto) && $userPhoto !== '')
        {
            return $userPhoto;
        }

        if (! class_exists(\App\Services\WhatsApp\WhatsAppProfilePhotoStore::class))
        {
            return null;
        }

        $phone = preg_replace('/[^0-9]/', '', (string) ($contact->phone ?? '')) ?? '';
        if ($phone === '' || (int) $contact->team_id < 1)
        {
            return null;
        }

        $whatsapp = app(\App\Services\WhatsApp\WhatsAppProfilePhotoStore::class)
            ->publicUrl((int) $contact->team_id, $phone);

        return is_string($whatsapp) && $whatsapp !== '' ? $whatsapp : null;
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
