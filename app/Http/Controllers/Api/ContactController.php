<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ImportMailerAudienceCsvRequest;
use App\Http\Requests\Api\StoreApiContactRequest;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactIntent;
use App\Models\ContactSentiment;
use App\Models\ContactStatus;
use App\Models\Country;
use App\Models\Module;
use App\Services\ApolloService;
use App\Services\Contacts\ContactProfileBuilder;
use App\Services\InboxContactAccessService;
use App\Services\MailerAudienceCsvImportService;
use App\Services\Prospecting\ProspectDirectory;
use App\Services\WhatsApp\WhatsAppThreadCategoryService;
use App\Support\SearchNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    public function store(StoreApiContactRequest $request): JsonResponse
    {
        $user = $request->user();
        $team = $user?->currentTeam;

        if (! $user || ! $team)
        {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no tiene equipo asignado',
            ], 400);
        }

        $validated = $request->validated();
        $digits = preg_replace('/[^\d]/', '', (string) ($validated['phone'] ?? ''));
        $phone = is_string($digits) && $digits !== '' ? (int) $digits : null;
        $email = trim((string) ($validated['email'] ?? ''));
        $leadId = (int) (ContactStatus::query()->where('name', 'Lead')->value('id') ?? 1);

        $contact = Contact::query()->create([
            'team_id' => $team->id,
            'name' => trim((string) $validated['name']),
            'surname' => trim((string) ($validated['surname'] ?? '')) ?: null,
            'email' => $email !== '' ? Str::lower($email) : null,
            'phone' => $phone,
            'language' => $this->defaultLanguageCode(),
            'country' => $this->defaultCountryId(),
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => $leadId,
        ]);

        $categoryId = isset($validated['category_id']) ? (int) $validated['category_id'] : 0;
        if ($categoryId > 0)
        {
            $validIds = Category::onlyExistingIds([$categoryId]);
            if ($validIds !== [])
            {
                $contact->categories()->sync($validIds);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
            ],
        ], 201);
    }

    public function importSchema(Request $request, MailerAudienceCsvImportService $importer): JsonResponse
    {
        $user = $request->user();
        $team = $user?->currentTeam;

        if (! $user || ! $team)
        {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no tiene equipo asignado',
            ], 400);
        }

        $this->authorize('create', Contact::class);

        return response()->json([
            'success' => true,
            'data' => [
                'required_columns' => MailerAudienceCsvImportService::REQUIRED_COLUMNS,
                'optional_columns' => MailerAudienceCsvImportService::OPTIONAL_COLUMNS,
                'sample_csv' => $importer->templateContents(),
                ...$importer->importOptions((int) $team->id),
            ],
        ]);
    }

    public function import(ImportMailerAudienceCsvRequest $request, MailerAudienceCsvImportService $importer): JsonResponse
    {
        $user = $request->user();
        $team = $user?->currentTeam;

        if (! $user || ! $team)
        {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no tiene equipo asignado',
            ], 400);
        }

        $this->authorize('create', Contact::class);

        $path = $request->file('file')->getRealPath();
        $countryId = (int) $request->input('country_id', MailerAudienceCsvImportService::DEFAULT_COUNTRY_ID);
        $categoryIds = $this->importCategoryIds($request);

        if ($request->boolean('preview'))
        {
            return response()->json([
                'success' => true,
                'data' => $importer->preview($path, $team, $this->importChoices($request), $countryId),
            ]);
        }

        $result = $importer->import(
            $path,
            $team,
            (int) $user->id,
            $this->importChoices($request),
            $countryId,
            $categoryIds,
        );

        if ($result['duplicates'] !== [])
        {
            return response()->json([
                'success' => false,
                'message' => 'Elegí qué teléfono conservar en los emails repetidos.',
                'data' => $result,
            ], 422);
        }

        $imported = $result['created'] + $result['updated'];

        return response()->json([
            'success' => $imported > 0,
            'message' => $imported > 0
                ? $result['created'].' creados, '.$result['updated'].' actualizados, '.$result['skipped'].' salteados.'
                : 'No se importó ningún contacto.',
            'data' => $result,
        ], $imported > 0 ? 200 : 422);
    }

    /**
     * Display a listing of the user's team contacts.
     */
    public function index(Request $request)
    {
        // Get the authenticated user
        $user = $request->user();

        if (! $user)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado',
            ], 401);
        }

        // Check if user has a current team
        if (! $user->currentTeam)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no tiene equipo asignado',
            ], 400);
        }

        try
        {
            // Check if user can view any contacts
            if (! $user->can('viewAny', Contact::class))
            {
                return response()->json([
                    'success' => false,
                    'error' => 'No tienes permisos para ver contactos',
                ], 403);
            }

            $query = Contact::with([
                'status:id,name,label_class',
                'responsible:id,name',
                'enterprises:id,name',
                'categories:id,name,color',
                'currentSentiment.sentiment',
                'currentSentiment.intent',
                'list60:id,contact_id',
            ]);

            $filterCallback = \App\Policies\ContactPolicy::getQueryFilter($user);
            $query = $filterCallback($query);

            $search = $request->get('search');
            if (is_string($search) && trim($search) !== '')
            {
                SearchNormalizer::applyContactNavbarConditions($query, trim($search));
            }

            $sentimentId = $this->numericFilter($request, 'sentiment_id');
            if ($sentimentId !== null)
            {
                $query->whereHas('currentSentiment', function ($q) use ($sentimentId)
                {
                    $q->where('sentiment_id', $sentimentId);
                });
            }

            $intentId = $this->numericFilter($request, 'intent_id');
            if ($intentId !== null)
            {
                $query->whereHas('currentSentiment', function ($q) use ($intentId)
                {
                    $q->where('intent_id', $intentId);
                });
            }

            $categoryId = $this->numericFilter($request, 'category_id');
            if ($categoryId !== null)
            {
                $query->whereHas('categories', function ($q) use ($categoryId)
                {
                    $q->where('categories.id', $categoryId);
                });
            }

            $countryId = $this->numericFilter($request, 'country');
            if ($countryId !== null)
            {
                $query->where('country', $countryId);
            }

            $statusId = $this->numericFilter($request, 'status_id');
            if ($statusId !== null)
            {
                $query->where('status_id', $statusId);
            }

            $query->orderBy('name')->orderBy('surname');

            $perPage = (int) $request->get('per_page', 10);
            $perPage = max(1, min($perPage, 100));

            $contacts = $query->paginate($perPage)->through(fn (Contact $contact) => $this->listItem($contact));

            return response()->json([
                'success' => true,
                'data' => $contacts,
                'show_list60' => (bool) $user->currentTeam?->hasModule('list60'),
                'team' => [
                    'id' => $user->currentTeam->id,
                    'name' => $user->currentTeam->name,
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()->name ?? 'user',
                ],
                'access_level' => $user->hasRole('admin') ? 'full' : ($user->hasRole('collaborator') ? 'team' : 'permission_based'),
            ]);
        } catch (\Exception $e)
        {
            \Log::error('Error obteniendo contactos vía API', [
                'user_id' => $user->id,
                'team_id' => $user->currentTeam->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Contact stats by status (Leads, En seguimiento, Clientes, Finalizados) for dashboard.
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        if (! $user)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado',
            ], 401);
        }

        if (! $user->currentTeam)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no tiene equipo asignado',
            ], 400);
        }

        if (! $user->can('viewAny', Contact::class))
        {
            return response()->json([
                'success' => false,
                'error' => 'No tienes permisos para ver contactos',
            ], 403);
        }

        $data = Contact::getContactStats($user->currentTeam->id);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Options for the contact list filters.
     */
    public function filters(Request $request)
    {
        $user = $request->user();

        if (! $user)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado',
            ], 401);
        }

        if (! $user->currentTeam)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no tiene equipo asignado',
            ], 400);
        }

        if (! $user->can('viewAny', Contact::class))
        {
            return response()->json([
                'success' => false,
                'error' => 'No tienes permisos para ver contactos',
            ], 403);
        }

        $moduleId = Module::query()->where('key', 'contacts')->value('id');

        return response()->json([
            'success' => true,
            'data' => [
                'sentiments' => ContactSentiment::getOptions()->values(),
                'intents' => ContactIntent::getOptions(),
                'categories' => Category::getOptions($user->currentTeam->id, null, $moduleId)->values(),
                'countries' => Country::query()
                    ->whereIn('id', Contact::query()->whereNotNull('country')->select('country'))
                    ->orderBy('name')
                    ->get(['id', 'name', 'code'])
                    ->map(fn (Country $country): array => [
                        'id' => $country->id,
                        'name' => $country->name,
                        'flag' => strtolower((string) $country->code),
                    ])
                    ->values(),
                'statuses' => ContactStatus::query()
                    ->orderBy('id')
                    ->get(['id', 'name'])
                    ->map(fn (ContactStatus $status): array => [
                        'id' => $status->id,
                        'name' => $status->name,
                    ])
                    ->values(),
            ],
        ]);
    }

    /**
     * Display the specified contact.
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user();

        if (! $user || ! $user->currentTeam)
        {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado o sin equipo',
            ], 401);
        }

        try
        {
            // Find the contact first
            $contact = Contact::where('id', $id)
                ->with(['status', 'creator', 'responsible', 'currentEnterprise', 'person.enrichments'])
                ->firstOrFail();

            // Check if user can view this specific contact
            if (! $user->can('view', $contact))
            {
                return response()->json([
                    'success' => false,
                    'error' => 'No tienes permisos para ver este contacto',
                ], 403);
            }

            $data = app(ContactProfileBuilder::class)->build($contact);

            return response()->json([
                'success' => true,
                'data' => $data,
                'access_level' => $user->hasRole('admin') ? 'full' : ($user->hasRole('collaborator') ? 'team' : 'permission_based'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e)
        {
            return response()->json([
                'success' => false,
                'error' => 'Contacto no encontrado',
            ], 404);
        } catch (\Exception $e)
        {
            \Log::error('Error obteniendo contacto vía API', [
                'user_id' => $user->id,
                'team_id' => $user->currentTeam->id,
                'contact_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
            ], 500);
        }
    }

    public function enrich(Request $request, string $id, ApolloService $apollo, ProspectDirectory $directory, ContactProfileBuilder $profiles): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->currentTeam)
        {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado o sin equipo',
            ], 401);
        }

        $contact = Contact::query()
            ->with(['currentEnterprise', 'person'])
            ->whereKey($id)
            ->first();

        if (! $contact || ! $user->can('view', $contact))
        {
            return response()->json([
                'success' => false,
                'message' => 'Contacto no encontrado',
            ], 404);
        }

        $organization = trim((string) ($contact->currentEnterprise?->name ?: $contact->person?->organization_name));
        if (! $profiles->canEnrich($contact, $organization))
        {
            return response()->json([
                'success' => false,
                'message' => 'Hace falta un email, o nombre, apellido y empresa, para enriquecer los datos.',
            ], 422);
        }

        try
        {
            $person = $apollo->matchPerson([
                'email' => $contact->email,
                'first_name' => $contact->name,
                'last_name' => $contact->surname,
                'organization_name' => $organization,
            ]);
        } catch (\RuntimeException $exception)
        {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'No se pudieron enriquecer los datos.',
            ], 422);
        }

        $apolloId = trim((string) ($person['id'] ?? ''));
        if ($person === null || $apolloId === '')
        {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró información de esta persona.',
            ], 422);
        }

        $title = trim((string) ($person['title'] ?? ''));
        if (trim((string) $contact->title) === '' && $title !== '')
        {
            $contact->title = mb_substr($title, 0, 255);
        }

        $foundOrganization = '';
        if (is_array($person['organization'] ?? null))
        {
            $foundOrganization = trim((string) ($person['organization']['name'] ?? ''));
        }

        $directory->storeImportedContact(
            $contact,
            (int) $user->currentTeam->id,
            ProspectDirectory::SOURCE_APOLLO,
            $apolloId,
            $person,
            $foundOrganization !== '' ? $foundOrganization : ($organization !== '' ? $organization : null),
            null,
        );

        $contact->refresh();

        return response()->json([
            'success' => true,
            'data' => $profiles->build($contact),
        ]);
    }

    private function numericFilter(Request $request, string $key): ?int
    {
        $value = $request->get($key);

        if ($value === null || $value === '' || ! is_numeric($value))
        {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function listItem(Contact $contact): array
    {
        $sentiment = $contact->currentSentiment?->sentiment;
        $intent = $contact->currentSentiment?->intent;
        $fullName = trim((string) $contact->name.' '.((string) ($contact->surname ?? '')));
        $enterprise = $contact->enterprises->first();

        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'surname' => $contact->surname,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'country' => $contact->country,
            'avatar_url' => $contact->avatarUrl(40),
            'enterprise' => $enterprise?->name,
            'enterprise_id' => $enterprise?->id,
            'user' => app(InboxContactAccessService::class)->presentForContact(auth()->user()?->currentTeam, $contact),
            'sentiment' => $sentiment ? [
                'id' => $sentiment->id,
                'name' => $sentiment->name,
                'emoji' => $sentiment->emoji,
            ] : null,
            'intent' => $intent ? [
                'id' => $intent->id,
                'name' => $intent->displayName(),
                'emoji' => $intent->emoji,
            ] : null,
            'categories' => $contact->categories->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'color' => WhatsAppThreadCategoryService::normalizeColor($category->color),
            ])->values(),
            'status' => $contact->status ? [
                'id' => $contact->status->id,
                'name' => $contact->status->name,
                'label_class' => $contact->status->label_class,
            ] : null,
            'responsible' => $contact->responsible ? [
                'id' => $contact->responsible->id,
                'name' => $contact->responsible->name,
            ] : null,
            'in_list60' => $contact->list60 !== null,
            'chat_path' => $this->chatPath($contact),
            'display_name' => $fullName !== '' ? $fullName : '—',
        ];
    }

    private function chatPath(Contact $contact): ?string
    {
        $url = $contact->chatIndexUrl();

        if (! is_string($url) || $url === '')
        {
            return null;
        }

        $parts = parse_url($url);
        $path = $parts['path'] ?? '/chat';

        if (isset($parts['query']))
        {
            $path .= '?'.$parts['query'];
        }

        return $path;
    }

    private function defaultCountryId(): int
    {
        return (int) (DB::table('countries')->where('id', 32)->value('id')
            ?? DB::table('countries')->value('id')
            ?? 32);
    }

    private function defaultLanguageCode(): string
    {
        return (string) (DB::table('languages')->where('code', 'es')->value('code')
            ?? DB::table('languages')->value('code')
            ?? 'es');
    }

    /**
     * @return array<string, string>
     */
    private function importChoices(Request $request): array
    {
        $raw = $request->input('choices');
        if (! is_string($raw) || trim($raw) === '')
        {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded))
        {
            return [];
        }

        $choices = [];
        foreach ($decoded as $email => $phone)
        {
            if (! is_string($email) || ! is_string($phone))
            {
                continue;
            }

            $choices[Str::lower(trim($email))] = trim($phone);
        }

        return $choices;
    }

    /**
     * @return list<int>
     */
    private function importCategoryIds(Request $request): array
    {
        $raw = $request->input('category_ids');
        if (! is_string($raw) || trim($raw) === '')
        {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded))
        {
            return [];
        }

        return array_values(array_filter(array_map('intval', $decoded)));
    }
}
