<?php

namespace App\Http\Controllers;

use App\DataTables\ContactDataTable;
use App\Enums\MessageDeliverySendProfile;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactSentiment;
use App\Models\ContactSentimentHistory;
use App\Models\ContactSource;
use App\Models\ContactStatus;
use App\Models\Country;
use App\Models\Enterprise;
use App\Models\EnterpriseDepartment;
use App\Models\EnterpriseStatus;
use App\Models\MessageDelivery;
use App\Models\Opportunity;
use App\Models\Source;
use App\Services\AstralChartService;
use App\Services\MessageDeliveryDispatcher;
use App\Support\CollectionMessagingGuide;
use App\Support\NewUserWelcomeEmailNotifier;
use App\Support\SearchNormalizer;
use App\Support\StripeInvoiceMetrics;
use App\Traits\TracksContactActions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Stripe\CreditNote;
use Stripe\Customer;
use Stripe\Invoice;
use Stripe\PaymentMethod;
use Stripe\Product;
use Stripe\Stripe;

class ContactController extends Controller
{
    use TracksContactActions;

    public function __construct()
    {
        // Note: Manual authorization in each method due to non-standard route parameter names
        // Laravel's authorizeResource() expects {contact} parameter, but routes use {id}
    }

    public function index(ContactDataTable $dataTable)
    {
        $this->authorize('viewAny', Contact::class);

        if (! auth()->user()->currentTeam)
        {
            return redirect()->route('error-without-team');
        }

        $teamId = auth()->user()->current_team_id;

        $data = Contact::getContactStats($teamId);
        $data['emotionalStates'] = ContactSentiment::getOptions();
        $data['enterpriseStatuses'] = ContactStatus::getOptions();

        return $dataTable->render('contact.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Contact::class);

        $data = new \stdClass;

        // Pre-fill user_id if provided in query string
        if (request()->has('link_user'))
        {
            $data->user_id = request()->input('link_user');
        }

        if (request()->filled('enterprise_id') && auth()->user()->currentTeam)
        {
            $prefillId = (int) request('enterprise_id');
            if ($prefillId > 0 && Enterprise::query()
                ->where('id', $prefillId)
                ->where('team_id', auth()->user()->current_team_id)
                ->exists())
            {
                $data->prefill_enterprise_id = $prefillId;
            }
        }

        $enterpriseStatuses = ContactStatus::getOptions();
        $enterpriseClientStatuses = EnterpriseStatus::getOptions(1);
        $socialSources = Source::getOptions();
        $teamEnterprises = $this->teamEnterprisesForContactForm();
        $enterpriseDepartments = EnterpriseDepartment::query()->orderBy('name')->get(['id', 'name']);

        return view('contact.form', compact('data', 'enterpriseStatuses', 'enterpriseClientStatuses', 'socialSources', 'teamEnterprises', 'enterpriseDepartments'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UpdateContactRequest $request)
    {
        $this->authorize('create', Contact::class);

        $data = $request->validated();

        $contactData = $data['contact'];

        $contactData['team_id'] = auth()->user()->currentTeam->id;
        $contactData['creator_id'] = auth()->user()->id;
        $contactData['responsible_id'] = $request->responsible_id;
        $contactData['email'] = $request->email;
        $contactData['phone'] = $request->phone ?: null;

        $contact = Contact::create($contactData);

        // Sync categories
        $categoryIds = [];
        if (isset($data['categories']))
        {
            $categoryIds = $data['categories'];
        }

        // Add default category if configured
        $defaultCategoryId = config('custom.default_contact_category_id');
        if ($defaultCategoryId)
        {
            $categoryIds[] = $defaultCategoryId;
        }

        $validCategoryIds = Category::onlyExistingIds(array_unique($categoryIds));
        if ($validCategoryIds !== [])
        {
            $contact->categories()->sync($validCategoryIds);
        }

        // Sync software
        if (isset($data['software_ids']))
        {
            $contact->softwares()->sync($data['software_ids']);
        }

        $this->syncContactEnterpriseFromForm($contact, $data['enterprise'] ?? []);

        if ($request->ajax())
        {
            return response()->json([
                'success' => true,
                'message' => __('messages.success.created'),
                'data' => $contact->fresh(),
            ]);
        }

        return redirect()
            ->route('contact.show', $contact->id)
            ->with('success', __('messages.success.created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data = Contact::with([
            'currentSentiment.sentiment',
            'creator',
            'responsible',
            'status',
            'country',
            'language',
            'sentimentHistories.sentiment',
            'enterprises',  // relación correcta
            'currentEnterprise',
            'user.roles',
            'user.currentTeam.settings',
            'contactInteractions' => function ($q)
            {
                $q->with('user:id,name')->orderByDesc('occurred_at')->limit(100);
            },
        ])->findOrFail($id);

        $this->authorize('view', $data);

        // Collaborators can only view their own assigned contacts
        $currentUser = auth()->user();
        if ($currentUser && $currentUser->hasRole('collaborator') && $data->responsible_id !== $currentUser->id)
        {
            abort(403);
        }

        // Verify current_enterprise_id belongs to this contact's enterprises
        if ($data->current_enterprise_id && $data->enterprises->isNotEmpty())
        {
            $hasCurrentEnterprise = $data->enterprises->contains('id', $data->current_enterprise_id);

            if (! $hasCurrentEnterprise)
            {
                // Current enterprise doesn't belong to this contact, set to first associated enterprise
                $data->current_enterprise_id = $data->enterprises->first()->id;
                $data->save();
                $data->load('currentEnterprise');
            }
        }

        // If contact has enterprises but no current_enterprise_id set, set it to the first one
        if ($data->enterprises->isNotEmpty() && ! $data->current_enterprise_id)
        {
            $data->current_enterprise_id = $data->enterprises->first()->id;
            $data->save();
            // Reload the relationship
            $data->load('currentEnterprise');
        }

        $team = auth()->user()->currentTeam->load('settings');

        $stripeData = [
            'subscription' => null,
            'customer' => null,
            'payment_method' => null,
            'invoices' => [],
            'metrics' => null,
        ];

        if ($team->getSetting('stripe_secret'))
        {
            $stripeData = [
                'public_key' => $team->getSetting('stripe_public'),
                'secret_key' => $team->getSetting('stripe_secret'),
                'webhook_secret' => $team->getSetting('stripe_webhook'),
                'subscription' => null,
                'customer' => null,
                'payment_method' => null,
                'invoices' => [],
                'unpaid_invoices' => [],
                'void_invoices' => [],
                'credit_notes' => [],
                'metrics' => null,
            ];

            // Determine the enterprise to use for Stripe (current or first associated)
            $enterpriseForStripe = $data->currentEnterprise ?: $data->enterprises->first();

            if ($enterpriseForStripe && $enterpriseForStripe->code)
            {
                try
                {
                    Stripe::setApiKey($team->getSetting('stripe_secret'));
                    // ... rest of the Stripe code ...
                } catch (\Exception $e)
                {
                    \Log::error('Error fetching Stripe data: '.$e->getMessage());
                }
            }
        }

        // Determine the enterprise again (outside to keep structure clear)
        $enterpriseForStripe = $data->currentEnterprise ?: $data->enterprises->first();

        if ($enterpriseForStripe && $enterpriseForStripe->code && $team->getSetting('stripe_secret'))
        {
            try
            {
                // Set secret key for backend operations
                Stripe::setApiKey($team->getSetting('stripe_secret'));

                // Retrieve customer
                $customer = Customer::retrieve([
                    'id' => $enterpriseForStripe->code,
                    'expand' => [
                        'subscriptions',
                        'subscriptions.data.items',
                        'tax_ids',
                    ],
                ]);

                // Get invoices by status
                $paidInvoices = Invoice::all([
                    'customer' => $customer->id,
                    'limit' => 20,
                    'status' => 'paid',
                ]);
                $openInvoices = Invoice::all([
                    'customer' => $customer->id,
                    'limit' => 20,
                    'status' => 'open',
                ]);
                $uncollectibleInvoices = Invoice::all([
                    'customer' => $customer->id,
                    'limit' => 20,
                    'status' => 'uncollectible',
                ]);
                $voidInvoices = Invoice::all([
                    'customer' => $customer->id,
                    'limit' => 20,
                    'status' => 'void',
                ]);

                // Credit notes (issued and/or void)
                $creditNotes = CreditNote::all([
                    'customer' => $customer->id,
                    'limit' => 20,
                ]);

                // Get payment methods
                $paymentMethods = PaymentMethod::all([
                    'customer' => $customer->id,
                    'type' => 'card',
                ]);

                $stripeData = [
                    'customer' => [
                        'name' => $customer->name,
                        'email' => $customer->email,
                        'created' => Carbon::createFromTimestamp($customer->created)->format('d/m/Y'),
                        'tax_ids' => array_map(function ($taxId)
                        {
                            return [
                                'type' => $taxId->type,
                                'value' => $taxId->value,
                                'country' => $taxId->country,
                            ];
                        }, $customer->tax_ids->data),
                    ],
                    'subscription' => null,
                    'payment_method' => null,
                    'invoices' => [],
                ];

                // Process subscriptions
                if ($customer->subscriptions && ! empty($customer->subscriptions->data))
                {
                    $stripeData['subscriptions'] = [];

                    foreach ($customer->subscriptions->data as $subscription)
                    {
                        // Get product details for each subscription
                        $product = Product::retrieve($subscription->items->data[0]->plan->product);

                        $statusTranslations = [
                            'active' => 'Activo',
                            'past_due' => 'Pago Vencido',
                            'canceled' => 'Cancelado',
                            'incomplete' => 'Incompleto',
                            'incomplete_expired' => 'Expirado',
                            'trialing' => 'En Prueba',
                            'unpaid' => 'No Pagado',
                        ];

                        $stripeData['subscriptions'][] = [
                            'id' => $subscription->id,
                            'status' => $subscription->status,
                            'status_translated' => $statusTranslations[$subscription->status] ?? ucfirst($subscription->status),
                            'current_period_start' => $subscription->current_period_start,
                            'current_period_end' => $subscription->current_period_end,
                            'amount' => $subscription->items->data[0]->price->unit_amount / 100,
                            'currency' => strtoupper($subscription->items->data[0]->price->currency),
                            'interval' => $subscription->items->data[0]->plan->interval,
                            'interval_count' => $subscription->items->data[0]->plan->interval_count,
                            'product_id' => $subscription->items->data[0]->plan->product,
                            'product_name' => $product->name,
                            'description' => $subscription->description ?? null,
                            'created' => $subscription->created,
                            'collection_method' => $subscription->collection_method,
                            'days_until_due' => $subscription->days_until_due,
                        ];
                    }
                }

                // Process payment method
                if (! empty($paymentMethods->data))
                {
                    $card = $paymentMethods->data[0]->card;
                    $stripeData['payment_method'] = [
                        'brand' => $card->brand,
                        'last4' => $card->last4,
                        'exp_month' => $card->exp_month,
                        'exp_year' => $card->exp_year,
                    ];
                }

                // Process invoices (paid)
                foreach ($paidInvoices->data as $invoice)
                {
                    $stripeData['invoices'][] = [
                        'id' => $invoice->id,
                        'number' => $invoice->number,
                        'amount' => $invoice->amount_paid / 100,  // Convert from cents
                        'currency' => strtoupper($invoice->currency),
                        'status' => $invoice->status,
                        'date' => Carbon::createFromTimestamp($invoice->created)->format('d/m/Y'),
                        'pdf' => $invoice->invoice_pdf,
                        'hosted_invoice_url' => $invoice->hosted_invoice_url,
                        'dashboard_url' => 'https://dashboard.stripe.com/invoices/'.$invoice->id,
                    ];
                }
                // Process invoices (unpaid: open + uncollectible)
                $stripeData['unpaid_invoices'] = [];
                foreach (array_merge($openInvoices->data, $uncollectibleInvoices->data) as $invoice)
                {
                    $stripeData['unpaid_invoices'][] = [
                        'id' => $invoice->id,
                        'number' => $invoice->number,
                        'amount' => ($invoice->amount_due ?? $invoice->amount_remaining ?? 0) / 100,
                        'currency' => strtoupper($invoice->currency),
                        'status' => $invoice->status,  // 'open' or 'uncollectible'
                        'date' => Carbon::createFromTimestamp($invoice->created)->format('d/m/Y'),
                        'pdf' => $invoice->invoice_pdf,
                        'hosted_invoice_url' => $invoice->hosted_invoice_url,
                        'dashboard_url' => 'https://dashboard.stripe.com/invoices/'.$invoice->id,
                    ];
                }

                // Process void invoices (canceled)
                foreach ($voidInvoices->data as $invoice)
                {
                    $stripeData['void_invoices'][] = [
                        'id' => $invoice->id,
                        'number' => $invoice->number,
                        'amount' => ($invoice->amount_due ?? 0) / 100,
                        'currency' => strtoupper($invoice->currency),
                        'status' => $invoice->status,  // 'void'
                        'date' => Carbon::createFromTimestamp($invoice->created)->format('d/m/Y'),
                        'pdf' => $invoice->invoice_pdf,
                        'hosted_invoice_url' => $invoice->hosted_invoice_url,
                        'dashboard_url' => 'https://dashboard.stripe.com/invoices/'.$invoice->id,
                    ];
                }

                // Process credit notes
                foreach ($creditNotes->data as $note)
                {
                    $stripeData['credit_notes'][] = [
                        'number' => $note->number,
                        'amount' => ($note->amount ?? 0) / 100,
                        'currency' => strtoupper($note->currency),
                        'status' => $note->status,  // 'issued' or 'void'
                        'date' => Carbon::createFromTimestamp($note->created)->format('d/m/Y'),
                        'pdf' => $note->pdf ?? null,
                    ];
                }

                // Calculate metrics — sum the same amounts shown in the tables (avoids StripeObject field quirks vs UI)
                $allInvoicesForMetrics = array_merge($paidInvoices->data, $openInvoices->data, $uncollectibleInvoices->data);
                $contactCountryCode = $data->country?->code ? strtolower((string) $data->country->code) : null;
                $metricsCurrency = StripeInvoiceMetrics::displayCurrencyForStripeInvoiceGroups(
                    $paidInvoices->data,
                    $openInvoices->data,
                    $uncollectibleInvoices->data,
                    'EUR',
                    $contactCountryCode,
                );

                $paidByCurrency = StripeInvoiceMetrics::sumAmountsByCurrency($stripeData['invoices']);
                $unpaidByCurrency = StripeInvoiceMetrics::sumAmountsByCurrency($stripeData['unpaid_invoices']);
                $totalPaid = array_sum($paidByCurrency);
                $totalUnpaid = array_sum($unpaidByCurrency);

                $firstInvoiceDate = null;
                foreach ($allInvoicesForMetrics as $invoice)
                {
                    if (! $firstInvoiceDate || $invoice->created < $firstInvoiceDate)
                    {
                        $firstInvoiceDate = $invoice->created;
                    }
                }

                $lifetimeMonths = $firstInvoiceDate
                    ? Carbon::createFromTimestamp($firstInvoiceDate)->diffInMonths(Carbon::now()) + 1
                    : 0;

                $ltv = $lifetimeMonths > 0 ? $totalPaid / $lifetimeMonths : $totalPaid;

                $baseAcquisitionCost = 50;
                $monthlyMarketingSpend = 10;
                $cac = $baseAcquisitionCost + ($monthlyMarketingSpend * $lifetimeMonths);

                $primaryDisplayCurrency = strtoupper((string) config('cashier.currency', 'usd'));

                $stripeData['metrics'] = [
                    'total_paid' => StripeInvoiceMetrics::formatMetricTotalsWithPrimaryEquivalent($paidByCurrency, $primaryDisplayCurrency),
                    'unpaid' => StripeInvoiceMetrics::formatMetricTotalsWithPrimaryEquivalent($unpaidByCurrency, $primaryDisplayCurrency),
                    'ltv' => number_format($ltv, 2),
                    'cac' => number_format($cac, 2),
                    'lifetime_months' => $lifetimeMonths,
                    'currency' => $metricsCurrency,
                ];

                if (! empty($stripeData['unpaid_invoices']))
                {
                    $stripeData['collection_guide'] = CollectionMessagingGuide::build(
                        $data,
                        $stripeData,
                        auth()->user()->currentTeam?->id,
                    );
                }
            } catch (\Exception $e)
            {
                \Log::error('Error fetching Stripe data: '.$e->getMessage());
            }
        }

        $trackingId = $this->startActionTracking($id, 'show');

        session([
            'tracking_id' => $trackingId,
            'viewing_contact_id' => $id,
            'previous_url' => url()->current(),
        ]);

        $sentiments = ContactSentiment::all();
        $totalSeconds = $data->calculateTotalAccumulatedSeconds();
        $enterpriseStatuses = ContactStatus::getOptions();
        $countries = Country::orderBy('name')->get();

        // Generate astral profile if birthday is available
        $astralProfile = null;
        if ($data->birthday)
        {
            $astralService = new AstralChartService;
            $countryName = $data->country ? \App\Models\Country::find($data->country)->name ?? null : null;
            $astralProfile = $astralService->generateAstralProfile($data->id, $data->birthday, $countryName);
        }

        $contactOpportunities = collect();
        if (auth()->user()->currentTeam?->hasModule('opportunities'))
        {
            $contactOpportunities = Opportunity::query()->where('contact_id', $data->id)->orderBy('name')->get();
        }

        return view(
            'contact.show',
            compact('data', 'trackingId', 'totalSeconds', 'sentiments', 'enterpriseStatuses', 'countries', 'stripeData', 'astralProfile', 'contactOpportunities'),
        );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $data = Contact::with('enterprises', 'sources', 'softwares', 'categories', 'currentEnterprise')->findOrFail($id);
        $this->authorize('update', $data);

        $data->birthday = $data->birthday ? Carbon::parse($data->birthday)->format('Y-m-d') : null;

        // Verify current_enterprise_id belongs to this contact's enterprises
        if ($data->current_enterprise_id && $data->enterprises->isNotEmpty())
        {
            $hasCurrentEnterprise = $data->enterprises->contains('id', $data->current_enterprise_id);

            if (! $hasCurrentEnterprise)
            {
                // Current enterprise doesn't belong to this contact, set to first associated enterprise
                $data->current_enterprise_id = $data->enterprises->first()->id;
                $data->save();
                $data->load('currentEnterprise');
            }
        }

        // If contact has enterprises but no current_enterprise_id set, set it to the first one
        if ($data->enterprises->isNotEmpty() && ! $data->current_enterprise_id)
        {
            $data->current_enterprise_id = $data->enterprises->first()->id;
            $data->save();
            // Reload the relationship
            $data->load('currentEnterprise');
        }

        $enterpriseStatuses = ContactStatus::getOptions();
        $enterpriseClientStatuses = EnterpriseStatus::getOptions(1);
        $socialSources = Source::getOptions();
        $teamEnterprises = $this->teamEnterprisesForContactForm();
        $enterpriseDepartments = EnterpriseDepartment::query()->orderBy('name')->get(['id', 'name']);

        return view('contact.form', compact('data', 'enterpriseStatuses', 'enterpriseClientStatuses', 'socialSources', 'teamEnterprises', 'enterpriseDepartments'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateContactRequest $request, $id)
    {
        $contact = Contact::with(['user.roles', 'user.currentTeam.settings'])->findOrFail($id);
        $this->authorize('update', $contact);

        $data = $request->validated();
        $contactData = $data['contact'];

        // Add responsible_id to the update data
        $contactData['responsible_id'] = $request->responsible_id;
        $contactData['email'] = $request->email;
        $contactData['phone'] = $request->phone ?: null;

        $contact = Contact::with(['user.roles', 'user.currentTeam.settings'])->findOrFail($id);
        $contact->update($contactData);

        // Sync enterprise relationship (many-to-many)
        if (isset($data['enterprise']['enterprise_id']))
        {
            $enterpriseId = $data['enterprise']['enterprise_id'];

            // If contact has only one enterprise, replace it. Otherwise, add without detaching
            if ($contact->enterprises()->count() <= 1)
            {
                // Replace the single enterprise
                $contact->enterprises()->sync([$enterpriseId]);
            } else
            {
                // Multiple enterprises: add without detaching existing ones
                $contact->enterprises()->syncWithoutDetaching([$enterpriseId]);
            }

            // Update current_enterprise_id if needed
            if (! $contact->current_enterprise_id)
            {
                $contact->update(['current_enterprise_id' => $enterpriseId]);
            }
        }

        // Sync categories
        if (isset($data['categories']))
        {
            $contact->categories()->sync(Category::onlyExistingIds($data['categories']));
        }

        // Sync software
        if (isset($data['software_ids']))
        {
            $contact->softwares()->sync($data['software_ids']);
        }

        $message = __('messages.success.updated');

        if ($request->ajax())
        {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $contact->fresh(),
            ]);
        }

        return redirect()
            ->route('contact.show', $contact->id)
            ->with('success', $message);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $model = Contact::findOrFail($id);
        $this->authorize('delete', $model);

        $model->delete();

        return response()->json(['success' => __('messages.success.deleted')], 200);
    }

    public function updateSentiment(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'sentiment_id' => 'required|exists:contact_sentiments,id',
            'notes' => 'required|string|max:255',
        ]);

        if ($validator->fails())
        {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $contact = Contact::findOrFail($id);

        ContactSentimentHistory::create([
            'contact_id' => $contact->id,
            'sentiment_id' => $request->sentiment_id,
            'notes' => $request->notes,
        ]);

        if ($contact->list60)
        {
            $newStatus = min($contact->list60->status_id + 1, 3);

            $contact->list60->update([
                'date_next' => now()->addDays(21),
                'status_id' => $newStatus,
            ]);
        }

        $newSentiment = ContactSentiment::find($request->sentiment_id);

        return response()->json([
            'message' => __('messages.success.sentiment_updated'),
            'newEmoji' => $newSentiment->emoji,
            'contactId' => $contact->id,
        ]);
    }

    /**
     * Update contact's astral birth data
     */
    public function updateAstralData(Request $request, string $id)
    {
        // Clean empty strings to null
        $data = $request->all();
        foreach (['birth_date', 'birth_time', 'birth_city', 'birth_latitude', 'birth_longitude'] as $field)
        {
            if (isset($data[$field]) && $data[$field] === '')
            {
                $data[$field] = null;
            }
        }

        $validator = Validator::make($data, [
            'birth_date' => 'nullable|date',
            'birth_time' => 'nullable|date_format:H:i',
            'birth_city' => 'nullable|string|max:255',
            'birth_latitude' => 'nullable|numeric|between:-90,90',
            'birth_longitude' => 'nullable|numeric|between:-180,180',
        ]);

        if ($validator->fails())
        {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $contact = Contact::with('astralProfile')->findOrFail($id);

        $this->authorize('update', $contact);

        // Update contact's birthday if provided
        if (isset($data['birth_date']))
        {
            $contact->birthday = $data['birth_date'];
            $contact->save();
        }

        // Use the updated birthday
        $birthDate = $contact->birthday;

        if (! $birthDate)
        {
            return response()->json([
                'message' => 'El contacto necesita una fecha de nacimiento antes de completar datos astrológicos.',
            ], 422);
        }

        // Update or create astral profile with new birth data
        $profile = $contact->astralProfile()->updateOrCreate(
            ['contact_id' => $contact->id],
            [
                'birth_date' => $birthDate,
                'birth_time' => $data['birth_time'] ?? null,
                'birth_city' => $data['birth_city'] ?? null,
                'birth_latitude' => $data['birth_latitude'] ?? null,
                'birth_longitude' => $data['birth_longitude'] ?? null,
            ],
        );

        // Regenerate astral calculations
        $astralService = new AstralChartService;
        $countryName = $contact->country ? \App\Models\Country::find($contact->country)->name ?? null : null;
        $astralService->generateAndSaveProfile($contact, $birthDate, $countryName);

        return response()->json([
            'message' => 'Datos astrológicos actualizados correctamente. Perfil recalculado.',
            'profile_complete' => $profile->fresh()->is_complete,
        ]);
    }

    public function UploadFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $teamUserId = auth()->user()->currentTeam->id.'-'.auth()->user()->id;

        $file->storeAs('contact/import', $teamUserId);
    }

    public function showImportForm()
    {
        $fileName = auth()->user()->currentTeam->id.'-'.auth()->user()->id;
        $filePath = storage_path('app/contact/import/'.$fileName);

        if (! file_exists($filePath))
        {
            return view('contact.import');
        }

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        $contacts = [];
        $totalImported = 0;

        foreach ($rows as $row)
        {
            if (! empty(array_filter($row)))
            {
                $contact = $this->detectFields($row);

                if ($contact['email'] || $contact['phone'])
                {
                    $contacts[] = $contact;

                    $newContact = $this->updateContact(
                        $contact['email'],
                        $contact['phone'],
                        $contact['name'],
                        $contact['socials'],
                    );

                    if ($newContact->wasRecentlyCreated)
                    {
                        $totalImported++;
                    }
                }
            }
        }

        $message = $totalImported.' contactos importados con éxito.';

        return redirect()
            ->route('contact-list')
            ->with('success', $message);
    }

    protected function updateContact($email, $phone, $name = null, $socials = [])
    {
        $contactData = [
            'name' => $name,
            'status_id' => 1,
            'team_id' => auth()->user()->currentTeam->id,
            'creator_id' => auth()->user()->id,
        ];

        $contactSources = [];

        if ($email)
        {
            $contactSources[] = [
                'source_id' => 1,
                'value' => $email,
            ];
        }
        if ($phone)
        {
            $contactSources[] = [
                'source_id' => 2,
                'value' => $phone,
            ];
        }

        foreach ($socials as $sourceId => $value)
        {
            $contactSources[] = [
                'source_id' => $sourceId,
                'value' => $value,
            ];
        }

        try
        {
            $contact = Contact::where('team_id', auth()->user()->currentTeam->id)
                ->where(function ($query) use ($email, $phone)
                {
                    if ($email)
                    {
                        $query->whereHas('sources', function ($subQuery) use ($email)
                        {
                            $subQuery
                                ->where('source_id', 1)
                                ->where('value', $email);
                        });
                    }
                    if ($phone)
                    {
                        $query->orWhereHas('sources', function ($subQuery) use ($phone)
                        {
                            $subQuery
                                ->where('source_id', 2)
                                ->where('value', $phone);
                        });
                    }
                })
                ->firstOrFail();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e)
        {
            $contact = Contact::create($contactData);

            foreach ($contactSources as $source)
            {
                ContactSource::create([
                    'contact_id' => $contact->id,
                    'source_id' => $source['source_id'],
                    'value' => $source['value'],
                ]);
            }
        }

        return $contact;
    }

    private function detectFields($values)
    {
        $contact = [
            'name' => null,
            'email' => null,
            'phone' => null,
            'socials' => [],
        ];

        $sources = Source::all()->keyBy('id');

        foreach ($values as $index => $value)
        {
            if (filter_var($value, FILTER_VALIDATE_EMAIL))
            {
                $contact['email'] = $value;
                unset($values[$index]);

                continue;
            }
        }

        foreach ($values as $index => $value)
        {
            if (preg_match('/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/', $value))
            {
                $contact['phone'] = $value;
                unset($values[$index]);

                continue;
            }
        }

        foreach ($values as $value)
        {
            if (! empty($value) && $value !== $contact['email'] && $value !== $contact['phone'])
            {
                foreach ($sources as $source)
                {
                    if (strpos($value, $source->base_url) === 0)
                    {
                        $contact['socials'][$source->id] = str_replace($source->base_url, '', $value);
                    }
                }
                if (is_null($contact['name']))
                {
                    $contact['name'] = $value;
                }
            }
        }

        return $contact;
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

    public function endAction($trackingId)
    {
        if (! $trackingId)
        {
            return response()->json(['success' => false, 'message' => 'No tracking ID provided']);
        }

        try
        {
            $this->endActionTracking($trackingId);

            return response()->json(['success' => true]);
        } catch (\Exception $e)
        {
            return response()->json(['success' => false, 'message' => 'Error ending tracking']);
        }
    }

    public function search(Request $request)
    {
        $query = trim($request->input('q'));
        $team = auth()->user()->currentTeam;

        $data = [
            'pages' => [
                [
                    'name' => config('variables.templateName').' CRM',
                    'icon' => 'ti-layout-grid',
                    'url' => 'dashboard/',
                ],
                [
                    'name' => 'Kanban',
                    'icon' => 'ti-layout-kanban',
                    'url' => 'app/kanban',
                ],
            ],
            'files' => [
                [
                    'name' => 'Class Attendance',
                    'subtitle' => 'By Tommy Shelby',
                    'src' => 'img/icons/misc/search-xls.png',
                    'meta' => '17kb',
                    'url' => 'javascript:;',
                ],
                [
                    'name' => 'Passport Image',
                    'subtitle' => 'By William Budd',
                    'src' => 'img/icons/misc/search-jpg.png',
                    'meta' => '35kb',
                    'url' => 'javascript:;',
                ],
                [
                    'name' => 'Class Notes',
                    'subtitle' => 'By Laurel Lance',
                    'src' => 'img/icons/misc/search-doc.png',
                    'meta' => '153kb',
                    'url' => 'javascript:;',
                ],
            ],
            'members' => [],
            'enterprises' => [],
            'services' => [],
            'projects' => [],
            'collaborators' => [],
            'invoices' => [],
        ];

        // Load initial data (all records) or filter by query
        $isInitialLoad = empty($query);

        // Only search contacts if the contacts module is active
        if ($team && $team->hasModule('contacts'))
        {
            $contactsQuery = Contact::select('id', 'name', 'surname', 'phone', 'email', 'status_id', 'created_at')
                ->with(['user.roles', 'user.teams', 'user.currentTeam.settings']);
            // No filter by status_id - include all contacts regardless of status

            if (! $isInitialLoad)
            {
                SearchNormalizer::applyContactNavbarConditions($contactsQuery, $query);
            }

            $data['members'] = $contactsQuery
                ->limit(20)  // Optimized limit for on-demand search
                ->get()
                ->map(function ($contact)
                {
                    $displayName = trim($contact->name.' '.$contact->surname);

                    return [
                        'name' => $displayName,
                        'subtitle' => $contact->email ?: 'Creado el '.($contact->created_at?->format('d-m-Y H:i:s') ?? '').' hs',
                        // remove avatar 'src' to simplify rendering
                        'url' => route('contact.show', $contact->id),
                    ];
                })
                ->values()
                ->all();

            // Add contact-related pages only if contacts module is active
            $data['pages'][] = [
                'name' => 'Contactos',
                'icon' => 'ti-users',
                'url' => 'contact/list',
            ];
        }

        // Search enterprises unconditionally (team scope still applies)

        /** @var \Illuminate\Database\Eloquent\Builder<\App\Models\Enterprise> $enterprisesQuery */
        $enterprisesQuery = \App\Models\Enterprise::select('id', 'name', 'code', 'phone', 'email', 'created_at', 'responsible_id');

        if (! $isInitialLoad)
        {
            SearchNormalizer::applyEnterpriseNavbarConditions($enterprisesQuery, $query);
        }

        $data['enterprises'] = $enterprisesQuery
            ->orderBy('name')
            ->limit(20)  // Optimized limit for on-demand search
            ->get()
            ->map(function ($enterprise)
            {
                return [
                    'name' => $enterprise->name,
                    'subtitle' => ($enterprise->code ? 'Código: '.$enterprise->code : 'Empresa creada el '.($enterprise->created_at?->format('d-m-Y H:i:s') ?? '').' hs'),
                    // remove icon 'src' to simplify rendering
                    'url' => route('empresas.show', $enterprise->id),
                ];
            })
            ->values()
            ->all();

        // Only search services if the services module is active
        if ($team && $team->hasModule('services'))
        {
            /** @var \Illuminate\Database\Eloquent\Builder<\App\Models\Service> $servicesQuery */
            $servicesQuery = \App\Models\Service::select('id', 'enterprise_id', 'description', 'data', 'status', 'created_at')
                ->where('status', 1);  // Only active services

            if (! $isInitialLoad)
            {
                SearchNormalizer::applyServiceNavbarConditions($servicesQuery, $query);
            }

            $data['services'] = $servicesQuery
                ->limit(20)  // Optimized limit for on-demand search
                ->get()
                ->map(function ($service)
                {
                    $serviceData = is_array($service->data) ? $service->data : (array) ($service->data ?? []);
                    $domain = $serviceData['domain'] ?? ($service->description ?: 'No domain');
                    $user = $serviceData['user'] ?? '';

                    return [
                        'name' => $domain,
                        'subtitle' => ! empty($user) ? "Usuario: {$user}" : 'Servicio creado el '.($service->created_at?->format('d-m-Y') ?? ''),
                        'src' => 'img/icons/brands/web.png',
                        'url' => route('service.show', $service->id),
                    ];
                })
                ->values()
                ->all();
        }

        // Only search projects if the projects module is active
        if ($team && $team->hasModule('projects'))
        {
            $projectsQuery = \App\Models\Project::with(['client', 'status'])
                ->select('id', 'name', 'real_name', 'description', 'enterprise_id', 'status_id', 'created_at');

            if (! $isInitialLoad)
            {
                SearchNormalizer::applyProjectNavbarConditions($projectsQuery, $query);
            }

            $data['projects'] = $projectsQuery
                ->limit(20)  // Optimized limit for on-demand search
                ->get()
                ->map(function ($project)
                {
                    $clientName = $project->client ? $project->client->name : 'Sin cliente';
                    $statusName = $project->status ? $project->status->name : 'Sin estado';

                    return [
                        'name' => $project->real_name ?: $project->name,
                        'subtitle' => "Cliente: {$clientName} - Estado: {$statusName}",
                        'src' => 'img/icons/brands/project.png',
                        'url' => route('project.show', $project->id),
                    ];
                })
                ->values()
                ->all();

            // Add project-related pages only if projects module is active
            $data['pages'][] = [
                'name' => 'Proyectos',
                'icon' => 'ti-folder',
                'url' => 'project/list',
            ];
        }

        // Only search collaborators if the collaborators module is active
        if ($team && $team->hasModule('collaborators'))
        {
            $collaboratorsQuery = Contact::query()
                ->whereHas('languageVariants')  // Only contacts with language variants (collaborators)
                ->whereHas('fares')  // Only contacts with services/fares
                ->select('id', 'name', 'created_at');

            if (! $isInitialLoad)
            {
                $collaboratorsQuery->where(function ($q) use ($query)
                {
                    SearchNormalizer::applyCollaboratorNameCondition($q, $query);
                });
            }

            $data['collaborators'] = $collaboratorsQuery
                ->get()
                ->map(function ($contact)
                {
                    return [
                        'name' => $contact->name,
                        'subtitle' => 'Colaborador creado el '.($contact->created_at?->format('d-m-Y H:i:s') ?? '').' hs',
                        'src' => 'img/avatars/collaborator.png',
                        'url' => route('collaborator.show', $contact->id),
                    ];
                })
                ->values()
                ->all();

            // Add collaborator-related pages only if collaborators module is active
            $data['pages'][] = [
                'name' => 'Colaboradores',
                'icon' => 'ti-user-group',
                'url' => 'collaborator/list',
            ];
        }

        // Only search invoices if the invoices module is active
        if ($team && $team->hasModule('invoices'))
        {
            $invoicesQuery = \App\Models\Invoice::with(['enterprise'])
                ->select('id', 'number', 'enterprise_id', 'created_at');

            if (! $isInitialLoad)
            {
                SearchNormalizer::applyColumnsNavbarConditions($invoicesQuery, ['number'], $query, null);
            }

            $data['invoices'] = $invoicesQuery
                ->limit(20)  // Optimized limit for on-demand search
                ->get()
                ->map(function ($invoice)
                {
                    $clientName = $invoice->enterprise ? $invoice->enterprise->name : 'Sin cliente';

                    return [
                        'name' => $invoice->number,
                        'subtitle' => "Cliente: {$clientName}",
                        'src' => 'img/icons/brands/invoice.png',
                        'url' => route('invoice.show', $invoice->id),
                    ];
                })
                ->values()
                ->all();

            // Add invoice-related pages only if invoices module is active
            $data['pages'][] = [
                'name' => 'Facturas',
                'icon' => 'ti-file-invoice',
                'url' => 'invoice/list',
            ];
        }

        // Search billing addresses
        if ($team && $team->hasModule('enterprises'))
        {
            $billingAddressesQuery = \App\Models\EnterpriseBillingAddress::with(['enterprise'])
                ->select('id', 'name', 'identification_number', 'enterprise_id', 'created_at');

            if (! $isInitialLoad)
            {
                SearchNormalizer::applyColumnsNavbarConditions($billingAddressesQuery, ['name', 'identification_number'], $query, null);
            }

            $billingAddresses = $billingAddressesQuery
                ->limit(20)  // Optimized limit for on-demand search
                ->get()
                ->map(function ($address)
                {
                    $enterpriseName = $address->enterprise ? $address->enterprise->name : 'Sin empresa';
                    $responsibleId = $address->enterprise?->responsible_id;

                    return [
                        'name' => $address->name,
                        'subtitle' => "Empresa: {$enterpriseName} - ID: {$address->identification_number}",
                        'src' => 'img/icons/brands/enterprise.png',
                        'url' => $responsibleId ? route('contact.show', $responsibleId) : '#',
                    ];
                })
                ->values()
                ->all();

            // Merge billing addresses into enterprises array
            $data['enterprises'] = array_merge($data['enterprises'], $billingAddresses);
        }

        // Add client-related pages only if clients module is active
        if ($team && $team->hasModule('clients'))
        {
            $data['pages'][] = [
                'name' => 'Clientes',
                'icon' => 'ti-user-heart',
                'url' => 'client/list',
            ];
        }

        // Add list60-related pages only if list60 module is active
        if ($team && $team->hasModule('list60'))
        {
            $data['pages'][] = [
                'name' => 'Lista de 60',
                'icon' => 'ti-list-check',
                'url' => 'list60/list',
            ];
        }

        return response()->json($data);
    }

    public function updateNotes(Request $request, $id)
    {
        $contact = Contact::findOrFail($id);
        $data = (array) ($contact->data ?? new \stdClass);
        $data['notes'] = $request->input('notes');

        $contact->update([
            'data' => $data,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notas actualizadas correctamente',
        ]);
    }

    public function importMapping()
    {
        $availableFields = ['name', 'email', 'phone'];

        return view('contact.import', compact('availableFields'));
    }

    public function uploadFileForMapping(Request $request)
    {
        try
        {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv,txt|mimetypes:text/plain,text/csv,text/x-csv,application/csv,application/x-csv,text/comma-separated-values,text/x-comma-separated-values,text/tab-separated-values,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);

            $file = $request->file('file');
            $teamUserId = auth()->user()->currentTeam->id.'-'.auth()->user()->id;

            // Ensure the directory exists
            $importDir = storage_path('app/contact/import');
            if (! file_exists($importDir))
            {
                mkdir($importDir, 0755, true);
            }

            $file->storeAs('contact/import', $teamUserId);

            $filePath = storage_path('app/contact/import/'.$teamUserId);

            if (! file_exists($filePath))
            {
                return redirect()->back()->with('error', 'Error al guardar el archivo.');
            }

            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            $rows = $worksheet->toArray();

            // Detect if first row is a header or data
            $firstRow = $rows[0] ?? [];
            $hasHeaders = $this->detectHeaders($firstRow);

            if ($hasHeaders)
            {
                // Remove header row
                $headers = array_shift($rows);
            } else
            {
                // Generate generic headers based on column count
                $columnCount = count($firstRow);
                $headers = [];
                for ($i = 0; $i < $columnCount; $i++)
                {
                    // Try to detect column type by looking at first data rows
                    $columnType = $this->detectColumnType($rows, $i);
                    $headers[] = $columnType ?: 'Columna '.($i + 1);
                }
            }

            $rows = array_filter($rows, function ($row)
            {
                return array_filter($row, function ($cell)
                {
                    return ! empty($cell) && $cell !== '' && $cell !== null;
                });
            });

            $rows = array_values($rows);
            shuffle($rows);

            $availableFields = [
                'name' => 'Nombre',
                'email' => 'Email',
                'phone' => 'Teléfono',
            ];

            // Get contact statuses
            $statuses = ContactStatus::getOptions();

            return view('contact.map', compact('headers', 'rows', 'availableFields', 'statuses'));
        } catch (\Exception $e)
        {
            \Log::error('Error uploading file for mapping: '.$e->getMessage(), [
                'file' => $request->file('file')?->getClientOriginalName(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Error al procesar el archivo: '.$e->getMessage());
        }
    }

    /**
     * Detect if the first row contains headers or data
     */
    private function detectHeaders($firstRow): bool
    {
        if (empty($firstRow))
        {
            return false;
        }

        // Check if first row looks like headers (contains common header terms)
        $headerKeywords = ['name', 'nombre', 'email', 'correo', 'mail', 'phone', 'telefono', 'teléfono', 'celular', 'mobile'];

        foreach ($firstRow as $cell)
        {
            if (! empty($cell))
            {
                $cellLower = strtolower(trim($cell));
                foreach ($headerKeywords as $keyword)
                {
                    if (strpos($cellLower, $keyword) !== false)
                    {
                        return true;
                    }
                }

                // If cell contains @ symbol, it's likely data (email), not a header
                if (strpos($cell, '@') !== false)
                {
                    return false;
                }

                // If cell is numeric and long enough, it's likely data (phone), not a header
                if (is_numeric($cell) && strlen($cell) >= 9)
                {
                    return false;
                }
            }
        }

        // Default: assume it's data if we can't determine
        return false;
    }

    /**
     * Detect column type by analyzing data in that column
     */
    private function detectColumnType($rows, $columnIndex): ?string
    {
        $sampleSize = min(10, count($rows));
        $emailCount = 0;
        $phoneCount = 0;

        for ($i = 0; $i < $sampleSize; $i++)
        {
            $value = $rows[$i][$columnIndex] ?? null;

            if (empty($value))
            {
                continue;
            }

            // Check if it's an email
            if (filter_var($value, FILTER_VALIDATE_EMAIL))
            {
                $emailCount++;
            }

            // Check if it's a phone number
            if (is_numeric($value) && strlen($value) >= 9 && strlen($value) <= 15)
            {
                $phoneCount++;
            }
        }

        // If most values are emails, suggest Email
        if ($emailCount >= $sampleSize * 0.7)
        {
            return 'Email';
        }

        // If most values are phone numbers, suggest Phone
        if ($phoneCount >= $sampleSize * 0.7)
        {
            return 'Phone';
        }

        return null;
    }

    public function processMapping(Request $request)
    {
        $request->validate([
            'status_id' => 'required|exists:contact_statuses,id',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id',
        ]);

        $teamUserId = auth()->user()->currentTeam->id.'-'.auth()->user()->id;
        $filePath = storage_path('app/contact/import/'.$teamUserId);

        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        $headers = array_shift($rows);
        $mapping = $request->input('mapping', []);
        $categories = $request->input('categories', []);
        $statusId = $request->input('status_id');

        $contactsCreated = 0;
        $contactsSkipped = 0;
        $contactsUpdated = 0;

        foreach ($rows as $row)
        {
            $mappedRow = [];
            $nameParts = [];
            $emailValue = null;
            $phoneValue = null;

            foreach ($mapping as $columnIndex => $field)
            {
                if (! empty($field))
                {
                    $value = $row[$columnIndex] ?? null;

                    if ($field === 'name')
                    {
                        $nameParts[] = trim($value);
                    } elseif ($field === 'email' && ! empty($value))
                    {
                        $emailValue = trim($value);
                    } elseif ($field === 'phone' && ! empty($value))
                    {
                        $phoneValue = trim($value);
                    }
                }
            }

            // Use email as name if no name is provided
            if (empty($nameParts) && ! empty($emailValue))
            {
                $nameParts[] = explode('@', $emailValue)[0];
            }

            if (! empty($nameParts))
            {
                $mappedRow['name'] = implode(' ', array_filter($nameParts));
            }

            // Store additional data from the import
            $additionalData = ['import' => []];
            foreach ($headers as $index => $header)
            {
                $value = $row[$index] ?? null;
                if (! empty($value))
                {
                    $additionalData['import'][$header] = $value;
                }
            }

            // Skip if no name and no email
            if (empty($mappedRow['name']) && empty($emailValue))
            {
                $contactsSkipped++;

                continue;
            }

            // Check if contact already exists by email
            $existingContact = null;
            if ($emailValue)
            {
                $existingContact = Contact::where('team_id', auth()->user()->currentTeam->id)
                    ->where('email', $emailValue)
                    ->first();
            }

            if ($existingContact)
            {
                // Update existing contact: sync categories only
                if (! empty($categories))
                {
                    $existingContact->categories()->syncWithoutDetaching($categories);
                }
                $contactsUpdated++;
            } else
            {
                // Create new contact
                $contact = Contact::create(array_merge($mappedRow, [
                    'team_id' => auth()->user()->currentTeam->id,
                    'creator_id' => auth()->user()->id,
                    'status_id' => $statusId,
                    'email' => $emailValue,
                    'phone' => $phoneValue ? (int) preg_replace('/[^0-9]/', '', $phoneValue) : null,
                    'data' => $additionalData,
                ]));

                // Attach categories to the contact
                if (! empty($categories))
                {
                    $contact->categories()->attach($categories);
                }

                $contactsCreated++;
            }
        }

        // Build success message
        $message = [];
        if ($contactsCreated > 0)
        {
            $message[] = "{$contactsCreated} contactos creados";
        }
        if ($contactsUpdated > 0)
        {
            $message[] = "{$contactsUpdated} contactos actualizados";
        }
        if ($contactsSkipped > 0)
        {
            $message[] = "{$contactsSkipped} registros omitidos (sin datos válidos)";
        }

        return redirect()
            ->route('contact-list')
            ->with('success', implode(', ', $message).'.');
    }

    /**
     * Link an existing user to a contact
     */
    public function linkUser(Request $request, $id)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $contact = Contact::with(['user.roles', 'user.currentTeam.settings'])->findOrFail($id);
        $user = \App\Models\User::with(['roles', 'teams', 'currentTeam.settings'])->findOrFail($request->user_id);

        // Check if user belongs to the same team
        if (! $user->teams->contains(auth()->user()->currentTeam->id))
        {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no pertenece al equipo actual',
            ], 422);
        }

        // Check if user is already linked to another contact
        $existingContact = Contact::with(['user.roles', 'user.teams', 'user.currentTeam.settings'])
            ->where('user_id', $user->id)
            ->first();
        if ($existingContact && $existingContact->id !== $contact->id)
        {
            return response()->json([
                'success' => false,
                'message' => 'Este usuario ya está vinculado a otro contacto',
            ], 422);
        }

        $contact->update(['user_id' => $user->id]);

        return response()->json([
            'success' => true,
            'message' => 'Usuario vinculado correctamente',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()->name ?? 'user',
            ],
        ]);
    }

    /**
     * Unlink user from contact
     */
    public function unlinkUser($id)
    {
        $contact = Contact::findOrFail($id);
        $contact->update(['user_id' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Usuario desvinculado correctamente',
        ]);
    }

    /**
     * Create a new user and link to contact
     */
    public function createAndLinkUser(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|exists:roles,name',
            'password' => 'required|string|min:8',
        ]);

        $contact = Contact::findOrFail($id);

        try
        {
            // Create the user
            $user = \App\Models\User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone ? preg_replace('/[^0-9]/', '', $request->phone) : null,
                'password' => \Hash::make($request->password),
                'current_team_id' => auth()->user()->currentTeam->id,
                'email_verified_at' => null,  // Force email verification
            ]);

            // Assign role
            $user->assignRole($request->role);

            // Add user to current team
            $user->teams()->attach(auth()->user()->currentTeam->id);

            // Link user to contact
            $contact->update(['user_id' => $user->id]);

            NewUserWelcomeEmailNotifier::queue($user, auth()->user()->currentTeam);

            return response()->json([
                'success' => true,
                'message' => 'Usuario creado y vinculado correctamente',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $request->role,
                ],
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el usuario: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show unified user linking page
     */
    public function showUserLinkPage($type, $id)
    {
        // Validate type
        if (! in_array($type, ['contact', 'collaborator']))
        {
            abort(404);
        }

        $contact = Contact::findOrFail($id);

        // Get available users for the current team
        $users = \App\Models\User::whereHas('teams', function ($q)
        {
            $q->where('team_id', auth()->user()->currentTeam->id);
        })->orderBy('name')->get();

        // Get available roles
        $roles = \Spatie\Permission\Models\Role::all();

        return view('user-link.show', compact('contact', 'users', 'roles', 'type'));
    }

    /**
     * Process user linking
     */
    public function processUserLink(Request $request, $type, $id)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $contact = Contact::with(['user.roles', 'user.currentTeam.settings'])->findOrFail($id);
        $user = \App\Models\User::with(['roles', 'teams', 'currentTeam.settings'])->findOrFail($request->user_id);

        // Check if user belongs to the same team
        if (! $user->teams->contains(auth()->user()->currentTeam->id))
        {
            return back()->withErrors(['user_id' => 'El usuario no pertenece al equipo actual']);
        }

        // Check if user is already linked to another contact
        $existingContact = Contact::with(['user.roles', 'user.teams', 'user.currentTeam.settings'])
            ->where('user_id', $user->id)
            ->first();
        if ($existingContact && $existingContact->id !== $contact->id)
        {
            return back()->withErrors(['user_id' => 'Este usuario ya está vinculado a otro contacto']);
        }

        $contact->update(['user_id' => $user->id]);

        $redirectRoute = $type === 'contact' ? 'contact.show' : 'collaborator.show';

        return redirect()->route($redirectRoute, $id)->with('success', 'Usuario vinculado correctamente');
    }

    /**
     * Process user creation and linking
     */
    public function processUserCreate(Request $request, $type, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|exists:roles,name',
            'password' => 'required|string|min:8',
        ]);

        $contact = Contact::findOrFail($id);

        try
        {
            // Create the user
            $user = \App\Models\User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone ? preg_replace('/[^0-9]/', '', $request->phone) : null,
                'password' => \Hash::make($request->password),
                'current_team_id' => auth()->user()->currentTeam->id,
                'email_verified_at' => null,  // Force email verification
            ]);

            // Assign role
            $user->assignRole($request->role);

            // Add user to current team
            $user->teams()->attach(auth()->user()->currentTeam->id);

            // Link user to contact
            $contact->update(['user_id' => $user->id]);

            $redirectRoute = $type === 'contact' ? 'contact.show' : 'collaborator.show';

            NewUserWelcomeEmailNotifier::queue($user, auth()->user()->currentTeam);

            return redirect()->route($redirectRoute, $id)->with('success', 'Usuario creado y vinculado correctamente');
        } catch (\Exception $e)
        {
            return back()->withErrors(['general' => 'Error al crear el usuario: '.$e->getMessage()]);
        }
    }

    /**
     * Show user unlink confirmation page
     */
    public function showUserUnlinkPage($type, $id)
    {
        // Validate type
        if (! in_array($type, ['contact', 'collaborator']))
        {
            abort(404);
        }

        $contact = Contact::with(['user.roles', 'user.teams', 'user.currentTeam.settings'])->findOrFail($id);

        // Check if contact has a linked user
        if (! $contact->user_id)
        {
            $redirectRoute = $type === 'contact' ? 'contact.show' : 'collaborator.show';

            return redirect()->route($redirectRoute, $id)->with('warning', 'Este '.$type.' no tiene un usuario vinculado');
        }

        $linkedUser = \App\Models\User::find($contact->user_id);

        return view('user-link.unlink', compact('contact', 'linkedUser', 'type'));
    }

    /**
     * Process user unlinking
     */
    public function processUserUnlink($type, $id)
    {
        $contact = Contact::with(['user.roles', 'user.teams', 'user.currentTeam.settings'])->findOrFail($id);
        $contact->update(['user_id' => null]);

        $redirectRoute = $type === 'contact' ? 'contact.show' : 'collaborator.show';

        return redirect()->route($redirectRoute, $id)->with('success', 'Usuario desvinculado correctamente');
    }

    /**
     * Resend a specific message delivery
     */
    public function resendDelivery(Request $request, $deliveryId)
    {
        try
        {
            $delivery = MessageDelivery::findOrFail($deliveryId);

            // Only allow resending if the delivery was actually sent
            if (! $delivery->sent_at || $delivery->sent_at->isFuture())
            {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden reenviar emails que ya han sido enviados',
                ], 400);
            }

            // Reset the existing delivery for resend (immediate sending)
            $delivery->update([
                'status_id' => 1,  // pending
                'sent_at' => now(),  // Send immediately for resend
                'delivered_at' => null,  // Reset delivery status
                'delivery_status' => null,  // Reset delivery status
                'email_provider' => null,  // Reset provider info
                'provider_message_id' => null,  // Reset provider message ID
            ]);

            app(MessageDeliveryDispatcher::class)->enqueue(
                delivery: $delivery,
                profile: MessageDeliverySendProfile::Message,
                withEnqueueJitter: false,
            );

            return response()->json([
                'success' => true,
                'message' => 'Email reenviado correctamente a '.$delivery->contact->email,
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error al reenviar email: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set the current enterprise for a contact
     */
    public function setCurrentEnterprise(Request $request, string $id)
    {
        $contact = Contact::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'enterprise_id' => 'required|exists:enterprises,id',
        ]);

        if ($validator->fails())
        {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify that the enterprise is actually associated with this contact
        if (! $contact->enterprises()->where('enterprises.id', $request->enterprise_id)->exists())
        {
            return response()->json([
                'success' => false,
                'message' => 'Esta empresa no está asociada con este contacto.',
            ], 403);
        }

        $contact->update([
            'current_enterprise_id' => $request->enterprise_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Empresa actual actualizada correctamente.',
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Enterprise>
     */
    private function teamEnterprisesForContactForm()
    {
        if (! auth()->user()?->currentTeam)
        {
            return collect();
        }

        return Enterprise::query()
            ->where('team_id', auth()->user()->current_team_id)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    /**
     * @param  array<string, mixed>  $enterpriseInput
     */
    private function syncContactEnterpriseFromForm(Contact $contact, array $enterpriseInput): void
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return;
        }

        $teamId = $team->id;
        $existingId = ! empty($enterpriseInput['enterprise_id']) ? (int) $enterpriseInput['enterprise_id'] : null;
        $departmentId = ! empty($enterpriseInput['department_id']) ? (int) $enterpriseInput['department_id'] : null;

        if ($existingId)
        {
            $enterprise = Enterprise::query()
                ->where('id', $existingId)
                ->where('team_id', $teamId)
                ->first();

            if ($enterprise)
            {
                $enterpriseUpdates = $this->enterpriseUpdatesFromFormInput($enterpriseInput);
                if ($enterpriseUpdates !== [])
                {
                    $enterprise->update($enterpriseUpdates);
                }

                $contact->enterprises()->sync([
                    $enterprise->id => [
                        'department_id' => $departmentId,
                    ],
                ]);

                if ((int) $contact->status_id === 5)
                {
                    $contact->update(['current_enterprise_id' => $enterprise->id]);
                }
            }

            return;
        }

        if (empty($enterpriseInput['name']))
        {
            return;
        }

        $enterprise = Enterprise::create([
            'team_id' => $teamId,
            'name' => $enterpriseInput['name'],
            'code' => $enterpriseInput['code'] ?? null,
            'website' => $enterpriseInput['website'] ?? null,
            'phone' => $enterpriseInput['phone'] ?? null,
            'email' => $enterpriseInput['email'] ?? null,
            'whatsapp' => $enterpriseInput['whatsapp'] ?? null,
            'status_id' => ! empty($enterpriseInput['status_id'])
                ? (int) $enterpriseInput['status_id']
                : ((int) $contact->status_id === 5 ? 2 : 1),
            'type_id' => 1,
            'responsible_id' => $contact->responsible_id,
            'creator_id' => auth()->id(),
        ]);

        $contact->enterprises()->sync([
            $enterprise->id => [
                'department_id' => $departmentId,
            ],
        ]);

        if ((int) $contact->status_id === 5)
        {
            $contact->update(['current_enterprise_id' => $enterprise->id]);
        }
    }

    /**
     * @param  array<string, mixed>  $enterpriseInput
     * @return array<string, mixed>
     */
    private function enterpriseUpdatesFromFormInput(array $enterpriseInput): array
    {
        $updates = [];

        foreach (['name', 'code', 'website', 'phone', 'email', 'whatsapp'] as $field)
        {
            if (! empty($enterpriseInput[$field]))
            {
                $updates[$field] = $enterpriseInput[$field];
            }
        }

        if (! empty($enterpriseInput['status_id']))
        {
            $updates['status_id'] = (int) $enterpriseInput['status_id'];
        }

        return $updates;
    }
}
