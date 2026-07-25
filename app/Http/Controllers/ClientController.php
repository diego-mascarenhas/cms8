<?php

namespace App\Http\Controllers;

use App\DataTables\ClientDataTable;
use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\EnterpriseDepartment;
use App\Models\EnterpriseStatus;
use App\Models\StripeSubscription;
use App\Policies\ContactPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Spatie\SimpleExcel\SimpleExcelReader;

class ClientController extends Controller
{
    public function index(ClientDataTable $dataTable)
    {
        if (! auth()->user()->currentTeam)
        {
            return redirect()->route('error-without-team');
        }

        $teamId = auth()->user()->current_team_id;

        $data = Enterprise::getContactStats($teamId);
        $data['enterpriseStatuses'] = EnterpriseStatus::getOptions(1);

        return $dataTable->render('client.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Enterprise::class);

        $enterpriseStatuses = EnterpriseStatus::getOptions(1);
        $placeData = session('place_data', []);
        $prefillData = array_filter([
            'name' => request()->query('name', ''),
            'email' => request()->query('email', ''),
            'phone' => request()->query('phone', ''),
            'website' => request()->query('website', ''),
            'code' => request()->query('code', ''),
        ], static fn ($value) => is_string($value) && trim($value) !== '');
        $linkSubscriptionId = request()->query('link_subscription_id');
        $data = (object) array_merge([
            'name' => '',
            'email' => '',
            'status_id' => 1,
            'address' => '',
            'postal_code' => '',
            'locality' => '',
            'province' => '',
            'country' => '',
            'phone' => '',
            'website' => '',
            'opening_hours' => '',
            'latitude' => '',
            'longitude' => '',
            'contact_person' => '',
            'link_subscription_id' => $linkSubscriptionId,
            'referred_by' => '',
        ], $placeData, $prefillData);
        $trackingId = session('client_form_tracking_id');
        $teamId = (int) auth()->user()->current_team_id;
        $rawReferredBy = (string) old('referred_by', is_string($data->referred_by ?? null) ? $data->referred_by : '');
        $referredBySelectValue = $this->canonicalReferredBySelectInput($rawReferredBy, $teamId);
        $referrerEnterpriseOptions = $this->referrerEnterpriseSelectOptions(
            null,
            $rawReferredBy,
            $teamId,
        );

        return view('client.form', compact('enterpriseStatuses', 'data', 'trackingId', 'referrerEnterpriseOptions', 'referredBySelectValue'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $teamId = auth()->user()->currentTeam->id;

        if ($request->filled('id'))
        {
            $enterprise = Enterprise::query()
                ->where('id', $request->id)
                ->where('team_id', $teamId)
                ->firstOrFail();

            $this->authorize('update', $enterprise);

            $statusFormTypeId = EnterpriseStatus::resolveFormEnterpriseTypeId($enterprise->type_id);
            $allowedStatusIds = EnterpriseStatus::getOptions($statusFormTypeId)->pluck('id')->all();

            $request->validate([
                'name' => 'required|string|min:3|max:75',
                'status_id' => ['required', 'integer', Rule::in($allowedStatusIds)],
                'email' => 'nullable|email',
                'website' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'whatsapp' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:255',
                'postal_code' => 'nullable|string|max:20',
                'locality' => 'nullable|string|max:50',
                'province' => 'nullable|string|max:50',
                'country' => 'nullable|string|max:100',
                'opening_hours' => 'nullable|string|max:2000',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'contact_person' => 'nullable|string|max:255',
                'referred_by' => 'nullable|string|max:255',
                'code' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('enterprises', 'code')
                        ->where(fn ($q) => $q->where('team_id', $teamId))
                        ->ignore($enterprise->id),
                ],
            ]);

            $enterprise->update([
                'name' => $request->name,
                'status_id' => (int) $request->status_id,
                'email' => $request->email,
                'website' => $request->website,
                'phone' => $request->phone,
                'whatsapp' => $request->whatsapp,
                'address' => $request->address,
                'postal_code' => $request->postal_code,
                'locality' => $request->locality,
                'province' => $request->province,
                'country' => $request->country,
                'referred_by' => $this->normalizeReferredByFromRequestInput($request->input('referred_by'), $teamId),
                'code' => $request->filled('code') ? trim((string) $request->code) : null,
                'data' => array_merge((array) ($enterprise->data ?? []), [
                    'opening_hours' => $request->input('opening_hours'),
                    'latitude' => $request->input('latitude'),
                    'longitude' => $request->input('longitude'),
                    'contact_person' => $request->input('contact_person'),
                ]),
            ]);

            return redirect()->route('client-list')->with('success', 'Record saved successfully.');
        }

        $this->authorize('create', Enterprise::class);

        $allowedStatusIds = EnterpriseStatus::getOptions(1)->pluck('id')->all();

        $data = $request->except(['id', '_token']);

        $request->validate([
            'name' => 'required|string|min:3|max:75',
            'email' => 'required|email',
            'website' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'locality' => 'nullable|string|max:50',
            'province' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:100',
            'opening_hours' => 'nullable|string|max:2000',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'contact_person' => 'nullable|string|max:255',
            'referred_by' => 'nullable|string|max:255',
            'code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('enterprises', 'code')
                    ->where(fn ($q) => $q->where('team_id', $teamId)),
            ],
            'data' => 'nullable|array',
            'status_id' => ['nullable', 'integer', Rule::in($allowedStatusIds)],
        ]);

        $data['team_id'] = $teamId;
        $data['status_id'] = $request->status_id ?? 1;
        $data['code'] = $request->filled('code') ? trim((string) $request->code) : null;
        $data['referred_by'] = $this->normalizeReferredByFromRequestInput($request->input('referred_by'), $teamId);

        $linkSubscriptionId = (int) $request->input('link_subscription_id', 0);
        if ($linkSubscriptionId > 0)
        {
            $linkSubscription = StripeSubscription::query()
                ->where('id', $linkSubscriptionId)
                ->where('team_id', $teamId)
                ->first();

            if ($linkSubscription && blank($data['code']) && filled($linkSubscription->customer_id))
            {
                // Creating from Stripe link flow: enforce customer_id as enterprise code.
                $data['code'] = (string) $linkSubscription->customer_id;
            }
        }

        $data['data'] = array_merge($data, [
            'opening_hours' => $request->input('opening_hours'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'contact_person' => $request->input('contact_person'),
        ]);

        $createdEnterprise = Enterprise::updateOrCreate(
            ['id' => $request->id],
            $data,
        );

        if ($linkSubscriptionId > 0)
        {
            return redirect()
                ->route('subscription.index')
                ->with('success', __('stripe_subscription.link.auto_link_success', [
                    'client' => $createdEnterprise->name,
                ]));
        }

        return redirect()->route('client-list')->with('success', 'Record saved successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $client = Enterprise::with([
            'responsible',
            'status',
            'enterpriseBillingAddresses.taxStatusType',
            'contacts' => function ($query)
            {
                $query->with(['status', 'user.roles']);
            },
            'projects.responsible',
            'projects.status',
            'projects.category',
            'services.currency',
            'services.category',
            'invoices.billingAddress',
            'invoices.currency',
        ])->findOrFail($id);

        $this->authorize('view', $client);

        // Separate active and past projects
        // Past projects: FINISHED (10), INVOICED (12), NOT_APPROVED (13)
        $pastProjectStatuses = [10, 12, 13];

        $activeProjects = $client->projects->filter(function ($project) use ($pastProjectStatuses)
        {
            return ! in_array($project->status_id, $pastProjectStatuses);
        });

        $pastProjects = $client->projects->filter(function ($project) use ($pastProjectStatuses)
        {
            return in_array($project->status_id, $pastProjectStatuses);
        });

        // Get services (relation; keep ordering stable for tables)
        $services = $client->services->sortBy('id')->values();

        $billingAddresses = $client->enterpriseBillingAddresses->sortByDesc('status')->values();

        $linkedContacts = $client->contacts->sortBy('name')->values();
        $enterpriseDepartments = EnterpriseDepartment::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $invoices = $client->invoices->sortByDesc('id')->values();

        $invoiceBalanceTotal = $client->invoices->sum('balance');

        return view('client.show', compact(
            'client',
            'activeProjects',
            'pastProjects',
            'services',
            'billingAddresses',
            'linkedContacts',
            'enterpriseDepartments',
            'invoices',
            'invoiceBalanceTotal',
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $row = Enterprise::query()
            ->where('id', $id)
            ->where('team_id', auth()->user()->current_team_id)
            ->first();

        if (! $row)
        {
            return redirect()->route('client-list')->with('error', 'Client not found.');
        }

        $this->authorize('edit', $row);

        $data = (object) array_merge($row->toArray(), (array) ($row->data ?? new \stdClass));
        $data->id = $id;

        $enterpriseStatuses = EnterpriseStatus::getOptions(1);
        $teamId = (int) auth()->user()->current_team_id;
        $rawReferredBy = (string) old('referred_by', $row->referred_by ?? '');
        $referredBySelectValue = $this->canonicalReferredBySelectInput($rawReferredBy, $teamId);
        $referrerEnterpriseOptions = $this->referrerEnterpriseSelectOptions(
            (int) $id,
            $rawReferredBy,
            $teamId,
        );

        return view('client.form', compact('data', 'enterpriseStatuses', 'referrerEnterpriseOptions', 'referredBySelectValue'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $model = Enterprise::findOrFail($id);

        $model->delete();

        return response()->json(['success' => 'The record has been deleted.'], 200);
    }

    public function importExcel(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $file = $request->file('excel_file');
        $path = $file->store('temp');
        $fullPath = Storage::path($path);

        $extension = $file->getClientOriginalExtension();

        try
        {
            if ($extension == 'csv')
            {
                $excel = SimpleExcelReader::create($fullPath, 'csv');
            } else
            {
                $excel = SimpleExcelReader::create($fullPath);
            }

            $rawData = [];
            $processedData = [];
            $updatedCount = 0;
            $duplicateCount = 0;
            $headers = null;

            foreach ($excel->getRows() as $index => $row)
            {
                $rawData[] = $row;

                if ($index === 0)
                {
                    if ($this->isHeaderRow($row))
                    {
                        $headers = array_map([$this, 'normalizeHeader'], array_keys($row));

                        continue;  // Skip header row
                    }
                }

                $values = array_values(array_filter($row));

                if (count($values) >= 2)  // At least name and email
                {$client = $this->detectFields($values);
                    $client['team_id'] = Auth::user()->currentTeam->id;

                    if ($headers)
                    {
                        $additionalData = array_slice($values, 3);
                        $additionalDataAssoc = [];

                        // Ensure both arrays have the same length
                        for ($i = 0; $i < count($additionalData); $i++)
                        {
                            if (isset($headers[$i + 3]))
                            {
                                $additionalDataAssoc[$headers[$i + 3]] = $additionalData[$i];
                            }
                        }

                        $client['data'] = ! empty($additionalDataAssoc) ? $additionalDataAssoc : null;
                    } else
                    {
                        $additionalData = array_slice($values, 3);
                        $client['data'] = ! empty($additionalData) ? $additionalData : null;
                    }

                    $validator = Validator::make($client, [
                        'name' => 'required|string',
                        'email' => 'required|email',
                        'phone' => 'nullable',
                    ]);

                    if ($validator->fails())
                    {
                        continue;  // Skip this row if validation fails
                    }

                    $existingClient = Enterprise::where('email', $client['email'])
                        ->where('team_id', $client['team_id'])
                        ->first();

                    if ($existingClient)
                    {
                        $existingClient->update($client);
                        $updatedCount++;
                    } else
                    {
                        Enterprise::create($client);
                        $processedData[] = $client;
                    }
                }
            }

            Storage::delete($path);

            return response()->json([
                'message' => 'Importación completada con éxito',
                'processed' => count($processedData),
                'updated' => $updatedCount,
                'duplicates' => $duplicateCount,
                'data' => $processedData,
                'rawData' => $rawData,
            ]);
        } catch (\Exception $e)
        {
            Storage::delete($path);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function detectFields($values)
    {
        $client = [
            'name' => null,
            'email' => null,
            'phone' => null,
        ];

        foreach ($values as $value)
        {
            if (filter_var($value, FILTER_VALIDATE_EMAIL))
            {
                $client['email'] = $value;
            } elseif (preg_match('/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/', $value))
            {
                $client['phone'] = $value;
            } else
            {
                $client['name'] = $value;
            }

            if ($client['name'] && $client['email'])
            {
                break;
            }
        }

        return $client;
    }

    private function isHeaderRow($row)
    {
        foreach ($row as $value)
        {
            if (! is_string($value))
            {
                return false;
            }
        }

        return true;
    }

    private function normalizeHeader($header)
    {
        $header = strtolower($header);
        $header = iconv('UTF-8', 'ASCII//TRANSLIT', $header);
        $header = preg_replace('/[^a-z0-9_]/', '_', $header);

        return $header;
    }

    /**
     * JSON list of team contacts not yet linked to this client (for attach modal).
     */
    public function linkableContacts(Request $request, string $id)
    {
        $enterprise = Enterprise::query()
            ->where('id', $id)
            ->where('team_id', auth()->user()->current_team_id)
            ->firstOrFail();

        $this->authorize('update', $enterprise);

        $linkedIds = $enterprise->contacts()->pluck('contacts.id');
        $search = trim((string) $request->query('q', ''));

        $query = Contact::query()
            ->where('team_id', $enterprise->team_id)
            ->when($linkedIds->isNotEmpty(), function ($q) use ($linkedIds)
            {
                $q->whereNotIn('id', $linkedIds);
            });

        (ContactPolicy::getQueryFilter(auth()->user()))($query);

        if ($search !== '')
        {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($like)
            {
                $q->where('name', 'like', $like)
                    ->orWhere('surname', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        $contacts = $query->orderBy('name')->orderBy('surname')->limit(50)->get(['id', 'name', 'surname', 'email', 'phone']);

        return response()->json(['contacts' => $contacts]);
    }

    /**
     * Attach an existing contact to this client (pivot contact_enterprise).
     */
    public function attachContact(Request $request, string $id)
    {
        $validated = $request->validate([
            'contact_id' => 'required|integer',
            'department_id' => 'nullable|integer|exists:enterprise_departments,id',
        ]);

        $enterprise = Enterprise::query()
            ->where('id', $id)
            ->where('team_id', auth()->user()->current_team_id)
            ->firstOrFail();

        $this->authorize('update', $enterprise);

        $query = Contact::query()
            ->where('team_id', $enterprise->team_id)
            ->whereKey($validated['contact_id']);

        (ContactPolicy::getQueryFilter(auth()->user()))($query);

        $contact = $query->firstOrFail();

        if ($contact->enterprises()->where('enterprises.id', $enterprise->id)->exists())
        {
            return response()->json([
                'success' => false,
                'message' => 'Este contacto ya está vinculado a este cliente.',
            ], 422);
        }

        $contact->enterprises()->syncWithoutDetaching([
            $enterprise->id => [
                'department_id' => $validated['department_id'] ?? null,
            ],
        ]);

        if ((int) $contact->status_id === 5 && ! $contact->current_enterprise_id)
        {
            $contact->update(['current_enterprise_id' => $enterprise->id]);
        }

        $contact->load(['user.roles']);

        $displayName = trim($contact->name.' '.($contact->surname ?? ''));

        return response()->json([
            'success' => true,
            'message' => 'Contacto vinculado correctamente.',
            'contact' => [
                'id' => $contact->id,
                'name' => $displayName,
                'email' => $contact->email ?: '',
                'phone' => $contact->phone ?: '',
                'roles' => $contact->user && $contact->user->roles->isNotEmpty()
                    ? $contact->user->roles->pluck('name')->join(', ')
                    : '',
            ],
        ]);
    }

    /**
     * Remove pivot link between this client and a contact.
     */
    public function detachContact(Request $request, string $id)
    {
        $validated = $request->validate([
            'contact_id' => 'required|integer',
        ]);

        $enterprise = Enterprise::query()
            ->where('id', $id)
            ->where('team_id', auth()->user()->current_team_id)
            ->firstOrFail();

        $this->authorize('update', $enterprise);

        $contact = Contact::query()
            ->where('team_id', $enterprise->team_id)
            ->whereKey($validated['contact_id'])
            ->firstOrFail();

        if (! $contact->enterprises()->where('enterprises.id', $enterprise->id)->exists())
        {
            return response()->json([
                'success' => false,
                'message' => 'Este contacto no está vinculado a este cliente.',
            ], 422);
        }

        $contact->enterprises()->detach($enterprise->id);
        $contact->unsetRelation('enterprises');

        if ((int) $contact->current_enterprise_id === (int) $enterprise->id)
        {
            $nextEnterprise = $contact->enterprises()->orderBy('enterprises.id')->first();
            $contact->update([
                'current_enterprise_id' => $nextEnterprise?->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contacto desvinculado correctamente.',
        ]);
    }

    public function showImportForm()
    {
        return view('client.import');
    }

    /**
     * Persisted referred_by: same-team pick is the referrer enterprise id (string); other teams may use a public code.
     */
    private function normalizeReferredByFromRequestInput(mixed $raw, int $teamId): ?string
    {
        $t = trim((string) ($raw ?? ''));
        if ($t === '')
        {
            return null;
        }

        if (ctype_digit($t))
        {
            $enterprise = Enterprise::query()
                ->where('team_id', $teamId)
                ->where('type_id', 1)
                ->where('id', (int) $t)
                ->first();
            if ($enterprise)
            {
                return (string) $enterprise->id;
            }
        }

        return $t;
    }

    /**
     * Map DB value to the select option value (prefer enterprise id for same-team referrers).
     */
    private function canonicalReferredBySelectInput(?string $stored, int $teamId): string
    {
        $s = trim((string) ($stored ?? ''));
        if ($s === '')
        {
            return '';
        }

        if (ctype_digit($s))
        {
            $exists = Enterprise::query()
                ->where('team_id', $teamId)
                ->where('type_id', 1)
                ->where('id', (int) $s)
                ->exists();
            if ($exists)
            {
                return $s;
            }
        }

        $byCode = Enterprise::query()
            ->where('team_id', $teamId)
            ->where('type_id', 1)
            ->where('code', $s)
            ->first();
        if ($byCode)
        {
            return (string) $byCode->id;
        }

        return $s;
    }

    /**
     * Options for referred_by: all client enterprises on this team; option value = enterprise id.
     * Optional extra row when the stored value is external / legacy (not an id on this team).
     *
     * @return list<array{value: string, label: string, disabled?: bool}>
     */
    private function referrerEnterpriseSelectOptions(?int $excludeEnterpriseId, string $persistedOrOldReferredBy, int $teamId): array
    {
        $query = Enterprise::query()
            ->where('team_id', $teamId)
            ->where('type_id', 1)
            ->orderBy('name');

        if ($excludeEnterpriseId)
        {
            $query->where('id', '!=', $excludeEnterpriseId);
        }

        $rows = [];
        foreach ($query->get(['id', 'name']) as $enterprise)
        {
            $rows[] = [
                'value' => (string) $enterprise->id,
                'label' => $enterprise->name,
            ];
        }

        $extra = trim($persistedOrOldReferredBy);
        if ($extra !== '')
        {
            $resolved = $this->canonicalReferredBySelectInput($extra, $teamId);
            if (! $this->referrerSelectRowsContainValue($rows, $resolved)
                && ! $this->referrerSelectRowsContainValue($rows, $extra))
            {
                $rows[] = [
                    'value' => $extra,
                    'label' => __('External referrer value').': '.$extra,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  list<array{value: string, label: string, disabled?: bool}>  $rows
     */
    private function referrerSelectRowsContainValue(array $rows, string $value): bool
    {
        foreach ($rows as $row)
        {
            if (($row['value'] ?? '') === $value)
            {
                return true;
            }
        }

        return false;
    }
}
