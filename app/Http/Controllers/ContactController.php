<?php

namespace App\Http\Controllers;

use App\DataTables\ContactDataTable;
use App\Models\Contact;
use App\Models\ContactSentimentHistory;
use App\Models\ContactStatus;
use App\Models\ContactSentiment;
use App\Models\Country;
use App\Models\Source;
use Illuminate\Http\Request;
use Spatie\SimpleExcel\SimpleExcelReader;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\TracksContactActions;
use App\Http\Requests\UpdateContactRequest;

use Carbon\Carbon;

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

		return view('contact.form', compact('data', 'enterpriseStatuses'));
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

		$contact = Contact::create($contactData);

		if (!empty($data['sources']))
		{
		}

		$message = 'Contact created successfully.';

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
		])->find($id);

		if (!$data)
		{
			return redirect()
				->route('contact-list')
				->with('error', 'Contact not found.');
		}

		$sentiments = ContactSentiment::all();
		$trackingId = $this->startActionTracking($id, 'show');
		$totalSeconds = $data->calculateTotalAccumulatedSeconds();

		$enterpriseStatuses = ContactStatus::getOptions();
		$countries = Country::orderBy('name')->get();

		return view(
			'contact.show',
			compact('data', 'trackingId', 'totalSeconds', 'sentiments', 'enterpriseStatuses', 'countries')
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
		dd($request->all());
		$data = $request->validated();

		$contactData = $data['contact'];

		$contact = Contact::findOrFail($id);
		$contact->update($contactData);

		if (!empty($data['sources']))
		{
		}

		$message = 'Contact updated successfully.';

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

		return response()->json(['success' => 'The record has been deleted.'], 200);
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

		$newSentiment = ContactSentiment::find($request->sentiment_id);

		return response()->json([
			'message' => 'Sentiment updated successfully.',
			'newEmoji' => $newSentiment->emoji,
			'contactId' => $contact->id,
		]);
	}

	public function importExcel(Request $request)
	{
		$request->validate([
			'excel_file' => 'required|file|mimes:xlsx,xls,csv',
		]);

		$file = $request->file('excel_file');
		$path = $file->store('temp');
		$fullPath = Storage::path($path);

		$extension = $file->getContactOriginalExtension();

		try
		{
			if ($extension == 'csv')
			{
				$excel = SimpleExcelReader::create($fullPath, 'csv');
			}
			else
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
						continue; // Skip header row
					}
				}

				$values = array_values(array_filter($row));

				if (count($values) >= 2)
				{
					// At least name and email
					$contact = $this->detectFields($values);
					$contact['team_id'] = Auth::user()->currentTeam->id;

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

						$contact['data'] = !empty($additionalDataAssoc) ? $additionalDataAssoc : null;
					}
					else
					{
						$additionalData = array_slice($values, 3);
						$contact['data'] = !empty($additionalData) ? $additionalData : null;
					}

					$validator = Validator::make($contact, [
						'name' => 'required|string',
						'email' => 'required|email',
						'phone' => 'nullable',
					]);

					if ($validator->fails())
					{
						continue; // Skip this row if validation fails
					}

					$existingContact = Contact::where('email', $contact['email'])
						->where('team_id', $contact['team_id'])
						->first();

					if ($existingContact)
					{
						$existingContact->update($contact);
						$updatedCount++;
					}
					else
					{
						Contact::create($contact);
						$processedData[] = $contact;
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
		}
		catch (\Exception $e)
		{
			Storage::delete($path);
			return response()->json(['error' => $e->getMessage()], 500);
		}
	}

	private function detectFields($values)
	{
		$contact = [
			'name' => null,
			'email' => null,
			'phone' => null,
		];

		foreach ($values as $value)
		{
			if (filter_var($value, FILTER_VALIDATE_EMAIL))
			{
				$contact['email'] = $value;
			}
			elseif (preg_match('/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/', $value))
			{
				$contact['phone'] = $value;
			}
			else
			{
				$contact['name'] = $value;
			}

			if ($contact['name'] && $contact['email'])
			{
				break;
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

	public function showImportForm()
	{
		return view('contact.import');
	}

	public function endAction($trackingId)
	{
		$this->endActionTracking($trackingId);
		return response()->json(['success' => true]);
	}

	public function search(Request $request)
	{
		$query = $request->input('q');

		$members = Contact::where('name', 'like', "%{$query}%")
			->select('id', 'name', 'profile')
			->get()
			->map(function ($contact)
			{
				return [
					'name' => $contact->name,
					'subtitle' => $contact->profile,
					'src' => 'img/avatars/guru-meditating.jpg',
					'url' => route('contact.show', $contact->id),
				];
			});

		$data = [
			'pages' => [
				[
					'name' => 'Humano CRM',
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
}
