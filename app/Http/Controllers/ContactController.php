<?php

namespace App\Http\Controllers;

use App\DataTables\ContactDataTable;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\ContactSource;
use App\Models\ContactSentiment;
use App\Models\ContactSentimentHistory;
use App\Models\Country;
use App\Models\Source;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Traits\TracksContactActions;
use App\Http\Requests\UpdateContactRequest;
use PhpOffice\PhpSpreadsheet\IOFactory;

use Carbon\Carbon;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentMethod;
use Stripe\Invoice;
use Stripe\Product;

class ContactController extends Controller
{
	use TracksContactActions;

	public function index(ContactDataTable $dataTable)
	{
		if (!auth()->user()->currentTeam)
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
		$data = new \stdClass();

		$enterpriseStatuses = ContactStatus::getOptions();
		$socialSources = Source::getOptions();

		return view('contact.form', compact('data', 'enterpriseStatuses', 'socialSources'));
	}

	/**
	 * Store a newly created resource in storage.
	 */
	public function store(UpdateContactRequest $request)
	{
		$data = $request->validated();

		$contactData = $data['contact'];

		$contactData['team_id'] = auth()->user()->currentTeam->id;
		$contactData['creator_id'] = auth()->user()->id;
		$contactData['responsible_id'] = $request->responsible_id;

		$contact = Contact::create($contactData);

		$message = __('messages.success.created');

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
			'enterprise'
		])->find($id);

		if (!$data)
		{
			return redirect()
				->route('contact-list')
				->with('error', __('messages.errors.not_found'));
		}

		$team = auth()->user()->currentTeam;

		$stripeData = [
			'subscription' => null,
			'customer' => null,
			'payment_method' => null,
			'invoices' => [],
			'metrics' => null
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
				'metrics' => null
			];

			if ($data->enterprise && $data->enterprise->code)
			{
				try
				{
					Stripe::setApiKey($team->getSetting('stripe_secret'));
					// ... rest of the Stripe code ...
				}
				catch (\Exception $e)
				{
					\Log::error('Error fetching Stripe data: ' . $e->getMessage());
				}
			}
		}

		if ($data->enterprise && $data->enterprise->code && $team->getSetting('stripe_secret'))
		{
			try
			{
				// Set secret key for backend operations
				Stripe::setApiKey($team->getSetting('stripe_secret'));

				// Retrieve customer
				$customer = Customer::retrieve([
					'id' => $data->enterprise->code,
					'expand' => [
						'subscriptions',
						'subscriptions.data.items',
						'tax_ids'
					]
				]);

				// Get invoices
				$invoices = Invoice::all([
					'customer' => $customer->id,
					'limit' => 10, // Last 10 invoices
					'status' => 'paid' // Only paid invoices
				]);

				// Get payment methods
				$paymentMethods = PaymentMethod::all([
					'customer' => $customer->id,
					'type' => 'card'
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
								'country' => $taxId->country
							];
						}, $customer->tax_ids->data)
					],
					'subscription' => null,
					'payment_method' => null,
					'invoices' => []
				];

				// Process subscriptions
				if ($customer->subscriptions && !empty($customer->subscriptions->data))
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
							'unpaid' => 'No Pagado'
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
							'days_until_due' => $subscription->days_until_due
						];
					}
				}

				// Process payment method
				if (!empty($paymentMethods->data))
				{
					$card = $paymentMethods->data[0]->card;
					$stripeData['payment_method'] = [
						'brand' => $card->brand,
						'last4' => $card->last4,
						'exp_month' => $card->exp_month,
						'exp_year' => $card->exp_year,
					];
				}

				// Process invoices
				foreach ($invoices->data as $invoice)
				{
					$stripeData['invoices'][] = [
						'number' => $invoice->number,
						'amount' => $invoice->amount_paid / 100, // Convert from cents
						'currency' => strtoupper($invoice->currency),
						'status' => $invoice->status,
						'date' => Carbon::createFromTimestamp($invoice->created)->format('d/m/Y'),
						'pdf' => $invoice->invoice_pdf
					];
				}

				// Calculate metrics
				$totalPaid = 0;
				$totalUnpaid = 0;
				$firstInvoiceDate = null;

				if (!empty($invoices->data))
				{
					foreach ($invoices->data as $invoice)
					{
						if ($invoice->status === 'paid')
						{
							$totalPaid += $invoice->amount_paid / 100;
						}
						else
						{
							$totalUnpaid += $invoice->amount_due / 100;
						}

						// Track first invoice date for customer age calculation
						if (!$firstInvoiceDate || $invoice->created < $firstInvoiceDate)
						{
							$firstInvoiceDate = $invoice->created;
						}
					}

					// Calculate customer lifetime in months
					$lifetimeMonths = $firstInvoiceDate ?
						Carbon::createFromTimestamp($firstInvoiceDate)->diffInMonths(Carbon::now()) + 1 : 0;

					// Calculate LTV (total revenue / number of months)
					$ltv = $lifetimeMonths > 0 ? $totalPaid / $lifetimeMonths : $totalPaid;

					// Calculate CAC (assuming a base acquisition cost plus monthly marketing spend)
					$baseAcquisitionCost = 50; // Coste de adquisición por cliente (50€)
					$monthlyMarketingSpend = 10; // Gasto mensual en marketing por cliente (10€)
					$cac = $baseAcquisitionCost + ($monthlyMarketingSpend * $lifetimeMonths);

					$stripeData['metrics'] = [
						'total_paid' => number_format($totalPaid, 2),
						'unpaid' => number_format($totalUnpaid, 2),
						'ltv' => number_format($ltv, 2),
						'cac' => number_format($cac, 2),
						'lifetime_months' => $lifetimeMonths
					];
				}

			}
			catch (\Exception $e)
			{
				\Log::error('Error fetching Stripe data: ' . $e->getMessage());
			}
		}

		$trackingId = $this->startActionTracking($id, 'show');

		session([
			'tracking_id' => $trackingId,
			'viewing_contact_id' => $id,
			'previous_url' => url()->current()
		]);

		$sentiments = ContactSentiment::all();
		$totalSeconds = $data->calculateTotalAccumulatedSeconds();
		$enterpriseStatuses = ContactStatus::getOptions();
		$countries = Country::orderBy('name')->get();

		return view(
			'contact.show',
			compact('data', 'trackingId', 'totalSeconds', 'sentiments', 'enterpriseStatuses', 'countries', 'stripeData')
		);
	}

	/**
	 * Show the form for editing the specified resource.
	 */
	public function edit($id)
	{
		$data = Contact::with('enterprise', 'sources')->findOrFail($id);
		$data->birthday = $data->birthday ? Carbon::parse($data->birthday)->format('Y-m-d') : null;
		$enterpriseStatuses = ContactStatus::getOptions();
		$socialSources = Source::getOptions();

		return view('contact.form', compact('data', 'enterpriseStatuses', 'socialSources'));
	}

	/**
	 * Update the specified resource in storage.
	 */
	public function update(UpdateContactRequest $request, $id)
	{
		$data = $request->validated();
		$contactData = $data['contact'];

		// Add responsible_id to the update data
		$contactData['responsible_id'] = $request->responsible_id;

		$contact = Contact::findOrFail($id);
		$contact->update($contactData);

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
				'status_id' => $newStatus
			]);
		}

		$newSentiment = ContactSentiment::find($request->sentiment_id);

		return response()->json([
			'message' => __('messages.success.sentiment_updated'),
			'newEmoji' => $newSentiment->emoji,
			'contactId' => $contact->id,
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
		$teamUserId = auth()->user()->currentTeam->id . '-' . auth()->user()->id;

		$file->storeAs('contact/import', $teamUserId);
	}

	public function showImportForm()
	{
		$fileName = auth()->user()->currentTeam->id . '-' . auth()->user()->id;
		$filePath = storage_path('app/contact/import/' . $fileName);

		if (!file_exists($filePath))
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
			if (!empty(array_filter($row)))
			{
				$contact = $this->detectFields($row);

				if ($contact['email'] || $contact['phone'])
				{
					$contacts[] = $contact;

					$newContact = $this->updateContact(
						$contact['email'],
						$contact['phone'],
						$contact['name'],
						$contact['socials']
					);

					if ($newContact->wasRecentlyCreated)
					{
						$totalImported++;
					}
				}
			}
		}

		$message = $totalImported . ' contactos importados con éxito.';

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
				'value' => $email
			];
		}
		if ($phone)
		{
			$contactSources[] = [
				'source_id' => 2,
				'value' => $phone
			];
		}

		foreach ($socials as $sourceId => $value)
		{
			$contactSources[] = [
				'source_id' => $sourceId,
				'value' => $value
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
							$subQuery->where('source_id', 1)
								->where('value', $email);
						});
					}
					if ($phone)
					{
						$query->orWhereHas('sources', function ($subQuery) use ($phone)
						{
							$subQuery->where('source_id', 2)
								->where('value', $phone);
						});
					}
				})
				->firstOrFail();
		}
		catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e)
		{
			$contact = Contact::create($contactData);

			foreach ($contactSources as $source)
			{
				ContactSource::create([
					'contact_id' => $contact->id,
					'source_id' => $source['source_id'],
					'value' => $source['value']
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
			if (!empty($value) && $value !== $contact['email'] && $value !== $contact['phone'])
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
			if (!is_string($value))
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
		if (!$trackingId)
		{
			return response()->json(['success' => false, 'message' => 'No tracking ID provided']);
		}

		try
		{
			$this->endActionTracking($trackingId);
			return response()->json(['success' => true]);
		}
		catch (\Exception $e)
		{
			return response()->json(['success' => false, 'message' => 'Error ending tracking']);
		}
	}

	public function search(Request $request)
	{
		$query = $request->input('q');

		$members = Contact::where('name', 'like', "%{$query}%")
			->select('id', 'name', 'created_at')
			->get()
			->map(function ($contact)
			{
				return [
					'name' => $contact->name,
					'subtitle' => 'Creado el ' . $contact->created_at->format('d-m-Y H:i:s') . ' hs',
					'src' => 'img/avatars/guru-meditating.jpg',
					'url' => route('contact.show', $contact->id),
				];
			});

		$data = [
			'pages' => [
				[
					'name' => config('variables.templateName') . ' CRM',
					'icon' => 'ti-layout-grid',
					'url' => 'dashboard/',
				],
				[
					'name' => 'Kanban',
					'icon' => 'ti-layout-kanban',
					'url' => 'app/kanban',
				],
				[
					'name' => 'Contactos',
					'icon' => 'ti-users',
					'url' => 'contact/list',
				],
				[
					'name' => 'Clientes',
					'icon' => 'ti-user-heart',
					'url' => 'client/list',
				],
				[
					'name' => 'Lista de 60',
					'icon' => 'ti-list-check',
					'url' => 'list60/list',
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
			'members' => $members,
		];

		return response()->json($data);
	}

	public function updateNotes(Request $request, $id)
	{
		$contact = Contact::findOrFail($id);
		$data = (array) ($contact->data ?? new \stdClass());
		$data['notes'] = $request->input('notes');

		$contact->update([
			'data' => $data
		]);

		return response()->json([
			'success' => true,
			'message' => 'Notas actualizadas correctamente'
		]);
	}

	public function importMapping()
	{
		$availableFields = ['name', 'email', 'phone'];

		return view('contact.import', compact('availableFields'));
	}

	public function uploadFileForMapping(Request $request)
	{
		$request->validate([
			'file' => 'required|file|mimes:xlsx,xls,csv',
		]);

		$file = $request->file('file');
		$teamUserId = auth()->user()->currentTeam->id . '-' . auth()->user()->id;

		$file->storeAs('contact/import', $teamUserId);

		$filePath = storage_path('app/contact/import/' . $teamUserId);
		$spreadsheet = IOFactory::load($filePath);
		$worksheet = $spreadsheet->getActiveSheet();

		$rows = $worksheet->toArray();
		$headers = array_shift($rows);

		$rows = array_filter($rows, function ($row)
		{
			return array_filter($row, function ($cell)
			{
				return !empty($cell) && $cell !== '' && $cell !== null;
			});
		});

		$rows = array_values($rows);
		shuffle($rows);

		$availableFields = [
			'name' => 'Nombre',
			'email' => 'Email',
			'phone' => 'Teléfono',
		];

		return view('contact.map', compact('headers', 'rows', 'availableFields'));
	}

	public function processMapping(Request $request)
	{
		$teamUserId = auth()->user()->currentTeam->id . '-' . auth()->user()->id;
		$filePath = storage_path('app/contact/import/' . $teamUserId);

		$spreadsheet = IOFactory::load($filePath);
		$worksheet = $spreadsheet->getActiveSheet();
		$rows = $worksheet->toArray();

		$headers = array_shift($rows);
		$mapping = $request->input('mapping', []);

		$contactsCreated = 0;

		foreach ($rows as $row)
		{
			$mappedRow = [];
			$sources = [];
			$nameParts = [];

			foreach ($mapping as $columnIndex => $field)
			{
				if (!empty($field))
				{
					$value = $row[$columnIndex] ?? null;

					if ($field === 'name')
					{
						$nameParts[] = trim($value);
					}
					else if ($field === 'email' && !empty($value))
					{
						$sources[] = [
							'source_id' => 1,
							'value' => $value
						];
					}
					else if ($field === 'phone' && !empty($value))
					{
						$sources[] = [
							'source_id' => 2,
							'value' => $value
						];
					}
				}
			}

			if (!empty($nameParts))
			{
				$mappedRow['name'] = implode(' ', array_filter($nameParts));
			}

			$additionalData = ['import' => []];
			foreach ($headers as $index => $header)
			{
				$value = $row[$index] ?? null;
				if (!empty($value))
				{
					$additionalData['import'][$header] = $value;
				}
			}

			if (!empty($mappedRow['name']))
			{
				$contact = Contact::create(array_merge($mappedRow, [
					'team_id' => auth()->user()->currentTeam->id,
					'creator_id' => auth()->user()->id,
					'status_id' => 1,
					'data' => $additionalData
				]));

				foreach ($sources as $source)
				{
					ContactSource::create([
						'contact_id' => $contact->id,
						'source_id' => $source['source_id'],
						'value' => $source['value']
					]);
				}

				$contactsCreated++;
			}
		}

		return redirect()->route('contact-list')
			->with('success', $contactsCreated . ' contactos importados correctamente.');
	}
}
