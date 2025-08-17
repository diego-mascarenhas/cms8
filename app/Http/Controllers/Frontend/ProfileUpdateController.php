<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\Contact;
use App\Models\ContactLanguageVariant;
use App\Models\User;
use App\Models\LanguageVariant;
use App\Models\Fare;
use App\Models\FareType;
use App\Models\Unit;
use App\Models\Language;
use App\Models\Software;
use Carbon\Carbon;

class ProfileUpdateController extends Controller
{
	public function index()
	{


		// Check if user is authenticated
		if (!Auth::check()) {
			return redirect()->route('login')->with('error', 'Please log in to access this page.');
		}

		// Check if user has collaborator role
		if (!Auth::user()->hasRole('collaborator')) {
			return redirect()->route('dashboard')->with('error', 'Access denied. Collaborator role required.');
		}

		// Get the collaborator's contact record
		$contact = Contact::where('user_id', Auth::id())->first();

		if (!$contact) {
			// Create a temporary contact record for testing if it doesn't exist
			$contact = Contact::create([
				'user_id' => Auth::id(),
				'team_id' => Auth::user()->currentTeam->id ?? 1,
				'creator_id' => Auth::id(), // Set creator_id to current user
				'name' => Auth::user()->name,
				'surname' => '',
				'email' => Auth::user()->email,
				'data' => json_encode([])
			]);

		}

		// Get existing data from JSON field
		$existingData = [];
		if ($contact->data) {
			// Handle both string and object formats
			if (is_string($contact->data)) {
				$existingData = json_decode($contact->data, true) ?: [];
			} elseif (is_object($contact->data) || is_array($contact->data)) {
				// Convert object to array recursively
				$existingData = json_decode(json_encode($contact->data), true) ?: [];
			}
		}

		// Add basic contact data to existingData
		$existingData['contact_info'] = [
			'first_name' => $contact->name,
			'last_name' => $contact->surname,
			'email' => $contact->email,
			'phone' => $contact->phone,
			'country' => $contact->data->country ?? 'ES',
			'timezone' => 'Europe/Madrid', // Default timezone
		];

		// Language variants are now handled by the x-variant-language-select component

		// Get fares (services) for the collaborator
		$fares = Fare::with(['type', 'units'])->get();

		// Get fare types
		$fareTypes = FareType::all();

		// Get units
		$units = Unit::all();

		// Get languages for base language selection
		$languages = Language::orderBy('name')->get();

		// Get base languages that have variants
		$baseLanguages = LanguageVariant::select('base_language')
			->groupBy('base_language')
			->havingRaw('COUNT(*) > 1')
			->pluck('base_language');

		// Get language objects for base languages
		$baseLanguageObjects = Language::whereIn('code', $baseLanguages)->get();

		// Get all language variants for the team (hardcoded to team_id 4)
		$allLanguageVariants = LanguageVariant::where('team_id', 4)->get();

		// Get languages for the base languages that have variants
		$languagesWithVariants = Language::whereIn('code', $baseLanguages)->get();

		// Get all languages for the team
		$allLanguages = Language::whereIn('code', $allLanguageVariants->pluck('base_language')->unique())->get();

				// Get collaborator's language variants
		$collaboratorLanguageVariants = $contact->languageVariants()->with(['sourceLanguage', 'targetLanguage'])->get();

				// Load existing rates from the pivot table
		$currentRatesData = [];
		$currentCurrency = 'EUR'; // Default currency
		$currentLanguagePair = '';

		if ($collaboratorLanguageVariants->count() > 0) {
			// Use the first language pair as default
			$firstVariant = $collaboratorLanguageVariants->first();
			$currentLanguagePair = $firstVariant->source_language_code . '|' . $firstVariant->target_language_code;

			// Load rates for the first language combination
			$existingRates = $contact->fares()
				->wherePivot('source_language_code', $firstVariant->source_language_code)
				->wherePivot('target_language_code', $firstVariant->target_language_code)
				->with('units')
				->get();

			// Build rates data array for the form
			foreach ($existingRates as $fare) {
				$currentRatesData[$fare->id] = [
					'price' => $fare->pivot->price,
					'unit_id' => $fare->pivot->unit_id,
					'currency_code' => $fare->pivot->currency_code,
				];

				// Set the currency from the first rate found
				if (!empty($fare->pivot->currency_code)) {
					$currentCurrency = $fare->pivot->currency_code;
				}
			}
		}

		// Software is handled by the x-software-select component

		// Get collaborator availability data
		$startDate = Carbon::now()->startOfMonth();
		$endDate = Carbon::now()->addMonths(5)->endOfMonth();

		$absences = $contact->absences()
			->whereBetween('absence_date', [$startDate, $endDate])
			->get()
			->pluck('absence_date')
			->map(function ($date) {
				return $date->format('Y-m-d');
			})
			->toArray();

		// Get weekly availability
		$weeklyAvailability = $contact->weeklyAvailability;
		if (!$weeklyAvailability) {
			// Create default availability if none exists
			$weeklyAvailability = $contact->weeklyAvailability()->create([
				'contact_id' => $contact->id,
				'monday' => true,
				'tuesday' => true,
				'wednesday' => true,
				'thursday' => true,
				'friday' => true,
				'saturday' => false,
				'sunday' => false,
				'team_id' => auth()->user()->currentTeam->id,
			]);
		}

		// Generate months for calendar
		$months = [];
		for ($i = 0; $i < 6; $i++) {
			$currentMonth = Carbon::now()->addMonths($i);
			$months[] = [
				'name' => $currentMonth->format('F Y'),
				'month' => $currentMonth->format('n'),
				'year' => $currentMonth->format('Y'),
				'firstDay' => $currentMonth->copy()->startOfMonth()->dayOfWeek,
				'daysInMonth' => $currentMonth->daysInMonth,
				'startPadding' => $currentMonth->copy()->startOfMonth()->dayOfWeekIso - 1,
			];
		}



		return view('frontend.profile-update.index', compact(
			'contact',
			'existingData',
			'fares',
			'fareTypes',
			'units',
			'languages',
			'baseLanguages',
			'baseLanguageObjects',
			'allLanguageVariants',
			'languagesWithVariants',
			'allLanguages',
			'collaboratorLanguageVariants',
			'currentRatesData',
			'currentCurrency',
			'currentLanguagePair',
			'absences',
			'weeklyAvailability',
			'months'
		));
	}

	public function store(Request $request)
	{
		// Check if user is authenticated
		if (!Auth::check()) {
			return redirect()->route('login')->with('error', 'Please log in to access this page.');
		}

		// Check if user has collaborator role
		if (!Auth::user()->hasRole('collaborator')) {
			return redirect()->route('dashboard')->with('error', 'Access denied. Collaborator role required.');
		}

				// Validate the request
		$validator = Validator::make($request->all(), [
			'first_name' => 'required|string|max:255',
			'last_name' => 'nullable|string|max:255',
			'email' => 'required|email|max:255',
			'phone' => 'nullable|string|max:255',
			'country' => 'nullable|string|max:255',
			'timezone' => 'required|string|max:255',
			'freelance_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
			'resume' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
			'password' => 'nullable|string|min:6|confirmed',
		]);

		if ($validator->fails()) {
			return back()->withErrors($validator)->withInput();
		}

		// Get the collaborator's contact record
		$contact = Contact::where('user_id', Auth::id())->first();

		if (!$contact) {
			// Create a temporary contact record for testing if it doesn't exist
			$contact = Contact::create([
				'user_id' => Auth::id(),
				'team_id' => Auth::user()->currentTeam->id ?? 1,
				'creator_id' => Auth::id(), // Set creator_id to current user
				'name' => $request->first_name,
				'surname' => $request->last_name,
				'email' => $request->email,
				'data' => json_encode([])
			]);
		}

		// Handle file uploads
		$uploadedFiles = [];

		if ($request->hasFile('freelance_certificate')) {
			$path = $request->file('freelance_certificate')->store('collaborators/certificates', 'public');
			$uploadedFiles['freelance_certificate'] = $path;
		}

		if ($request->hasFile('resume')) {
			$path = $request->file('resume')->store('collaborators/resumes', 'public');
			$uploadedFiles['resume'] = $path;
		}

		if ($request->hasFile('voice_sample')) {
			$path = $request->file('voice_sample')->store('collaborators/voice_samples', 'public');
			$uploadedFiles['voice_sample'] = $path;
		}

		// Handle additional voice samples
		if ($request->hasFile('extra_voice_samples')) {
			$extraVoiceSamples = [];
			foreach ($request->file('extra_voice_samples') as $file) {
				if ($file->isValid()) {
					$path = $file->store('collaborators/voice_samples', 'public');
					$extraVoiceSamples[] = $path;
				}
			}
			$uploadedFiles['extra_voice_samples'] = $extraVoiceSamples;
		}

		// Handle project files
		if ($request->hasFile('archivo_proyecto')) {
			$projectFiles = [];
			foreach ($request->file('archivo_proyecto') as $file) {
				if ($file->isValid()) {
					$path = $file->store('collaborators/projects', 'public');
					$projectFiles[] = $path;
				}
			}
			$uploadedFiles['project_files'] = $projectFiles;
		}

		// Prepare the data structure for JSON storage
		$profileData = [
			'password' => $request->password ? Hash::make($request->password) : null,
			'contact_info' => [
				'first_name' => $request->first_name,
				'last_name' => $request->last_name,
				'email' => $request->email,
				'phone' => $request->phone,
				'address' => $request->address,
				'city' => $request->city,
				'state' => $request->state,
				'country' => $request->country,
				'timezone' => $request->timezone,
			],
			'resume' => [
				'file' => $uploadedFiles['resume'] ?? null,
				'project_title' => $request->project_title,
				'project_role' => $request->project_role,
				'project_year' => $request->project_year,
				'project_languages' => $request->project_languages,
				'project_comments' => $request->project_comments,
			],
			'more_info' => [
				'software' => $request->software,
				'certification' => $request->certification,
				'other_certification' => $request->other_certification,
			],
			'voice_acting' => [
				'voice_sample' => $uploadedFiles['voice_sample'] ?? null,
				'extra_voice_samples' => $uploadedFiles['extra_voice_samples'] ?? [],
			],
			'language_pairs' => $this->processLanguagePairs($request->input('language_pairs', [])),
			'rates' => [
				'rate_options' => $request->rate_options,
				'currency' => $request->currency,
				'rate_type' => $request->rate_type,
				'template_translation' => $request->rate_template_translation,
				'translation_subtitling' => $request->rate_translation_subtitling,
				'subtitling_with_script' => $request->rate_subtitling_with_script,
				'voiceover_translation' => $request->rate_voiceover_translation,
				'literary_script' => $request->rate_literary_script,
				'transcreation' => $request->rate_transcreation,
				'transcription' => $request->rate_transcription,
				'transcript_subtitle' => $request->rate_transcript_subtitle,
				'adapt_subtitle' => $request->rate_adapt_subtitle,
				'subtitle_review' => $request->rate_subtitle_review,
				'dubbing_adjustment' => $request->rate_dubbing_adjustment,
				'postediting' => $request->rate_postediting,
				'transcript_postedit' => $request->rate_transcript_postedit,
				'general' => $request->rate_general,
				'legal' => $request->rate_legal,
				'technical' => $request->rate_technical,
				'medical' => $request->rate_medical,
				'scientific' => $request->rate_scientific,
				'review' => $request->rate_review,
				'subtitles_scripted' => $request->rate_subtitles_scripted,
				'subtitles_unscripted' => $request->rate_subtitles_unscripted,
				'subtitles_adaptation' => $request->rate_subtitles_adaptation,
				'subtitles_review' => $request->rate_subtitles_review,
				'audio_script' => $request->rate_audio_script,
				'audio_voice' => $request->rate_audio_voice,
				'minimum' => $request->rate_minimum,
				'hourly' => $request->rate_hourly,
				'rates_flexible' => $request->rates_flexible,
			],
			'availability' => [
				'weekend_availability' => $request->weekend_availability,
				'unavailable_dates' => $request->unavailable_dates,
			],
			'files' => $uploadedFiles,
			'submitted_at' => now()->toISOString(),
		];

		// Update the contact's data field with the JSON
		$contact->update([
			'data' => json_encode($profileData),
			'name' => $request->first_name,
			'surname' => $request->last_name,
			'email' => $request->email,
			'phone' => $request->phone,
		]);

		// Process and save language pairs to ContactLanguageVariant table
		if ($request->has('language_pairs') && is_array($request->language_pairs) && count($request->language_pairs) > 0) {
			// Delete existing language pairs for this contact
			$contact->languageVariants()->delete();

			$processedPairs = []; // To avoid duplicates

			// Add new language pairs
			foreach ($request->language_pairs as $pair) {
				if (empty($pair)) {
					continue;
				}

				$parts = explode('|', $pair);
				if (count($parts) !== 2) continue;

				[$sourceLanguage, $targetLanguage] = $parts;

				// Avoid duplicates in the same request
				$pairKey = $sourceLanguage.'-'.$targetLanguage;
				if (in_array($pairKey, $processedPairs)) {
					continue;
				}

				$processedPairs[] = $pairKey;

				try {
					ContactLanguageVariant::create([
						'contact_id' => $contact->id,
						'source_language_code' => $sourceLanguage,
						'target_language_code' => $targetLanguage,
						'proficiency_level' => 3, // Default proficiency level
						'is_certified' => false, // Default to not certified
					]);
				} catch (\Illuminate\Database\QueryException $e) {
					// If it's a duplicate error, simply ignore it
					if ($e->errorInfo[1] == 1062) {
						continue;
					}
					throw $e; // If it's another type of error, throw it
				}
			}
		} else {
			// If there are no language pairs, delete all existing ones
			$contact->languageVariants()->delete();
		}

		// Process and save rates to the pivot table (like backend does)
		$this->saveCollaboratorRates($request, $contact);

		// Update user password if provided
		if ($request->password) {
			Auth::user()->update([
				'password' => Hash::make($request->password)
			]);
		}

		return redirect()->route('profile-update.index')->with('success', 'Profile updated successfully. Your data has been submitted for admin review.');
	}

	/**
	 * Save collaborator rates to the pivot table
	 */
	private function saveCollaboratorRates(Request $request, $contact)
	{
		// Get rates and units from the request
		$rates = $request->input('rates', []);
		$units = $request->input('units', []);
		$currency = $request->input('currency', 'EUR');
		$sameRates = $request->has('same_rates'); // Check if "same rates" checkbox is checked
		$currentLanguagePair = $request->input('current_language_pair', '');

		// Only process if we have language pairs and rates
		if (empty($rates) || !$contact->languageVariants()->exists()) {
			return;
		}

		// Get collaborator's language variants
		$languageVariants = $contact->languageVariants;

		if ($sameRates) {
			// Same rates for ALL language combinations - OVERWRITE ALL
			$contact->fares()->detach(); // Delete all existing rates

			// Save the same rates for each language combination
			foreach ($languageVariants as $variant) {
				foreach ($rates as $fareId => $price) {
					// Only save rates that have a value greater than 0
					if (!empty($price) && $price > 0) {
						$attachData = [
							'price' => $price,
							'currency_code' => $currency,
							'unit_id' => $units[$fareId] ?? null,
							'source_language_code' => $variant->source_language_code,
							'target_language_code' => $variant->target_language_code,
							'created_at' => now(),
							'updated_at' => now(),
						];

						$contact->fares()->attach($fareId, $attachData);
					}
				}
			}
		} else {
			// Different rates per language combination - save only for the current active pair
			if (empty($currentLanguagePair)) {
				// If no specific language pair, use the first one
				$firstVariant = $languageVariants->first();
				if ($firstVariant) {
					$currentLanguagePair = $firstVariant->source_language_code . '|' . $firstVariant->target_language_code;
				}
			}

			if (!empty($currentLanguagePair)) {
				$parts = explode('|', $currentLanguagePair);
				if (count($parts) === 2) {
					[$sourceCode, $targetCode] = $parts;

					// Delete existing rates for ONLY this specific language combination
					$contact->fares()
						->wherePivot('source_language_code', $sourceCode)
						->wherePivot('target_language_code', $targetCode)
						->detach();

					// Save new rates for this specific language combination
					foreach ($rates as $fareId => $price) {
						// Only save rates that have a value greater than 0
						if (!empty($price) && $price > 0) {
							$attachData = [
								'price' => $price,
								'currency_code' => $currency,
								'unit_id' => $units[$fareId] ?? null,
								'source_language_code' => $sourceCode,
								'target_language_code' => $targetCode,
								'created_at' => now(),
								'updated_at' => now(),
							];

							$contact->fares()->attach($fareId, $attachData);
						}
					}
				}
			}
		}
	}

	/**
	 * Get rates for a specific language combination via AJAX
	 */
	public function getRatesForLanguagePair(Request $request)
	{
		$languagePair = $request->input('language_pair');

		if (empty($languagePair)) {
			return response()->json(['error' => 'Language pair required'], 400);
		}

		$parts = explode('|', $languagePair);
		if (count($parts) !== 2) {
			return response()->json(['error' => 'Invalid language pair format'], 400);
		}

		[$sourceCode, $targetCode] = $parts;

		// Get the collaborator's contact record
		$contact = Contact::where('user_id', Auth::id())->first();

		if (!$contact) {
			return response()->json(['error' => 'Contact not found'], 404);
		}

		// Load rates for this specific language combination
		$existingRates = $contact->fares()
			->wherePivot('source_language_code', $sourceCode)
			->wherePivot('target_language_code', $targetCode)
			->with('units')
			->get();

		$ratesData = [];
		$currency = 'EUR'; // Default

		// Build rates data array
		foreach ($existingRates as $fare) {
			$ratesData[$fare->id] = [
				'price' => $fare->pivot->price,
				'unit_id' => $fare->pivot->unit_id,
				'currency_code' => $fare->pivot->currency_code,
			];

			// Set the currency from the first rate found
			if (!empty($fare->pivot->currency_code)) {
				$currency = $fare->pivot->currency_code;
			}
		}

		return response()->json([
			'success' => true,
			'rates' => $ratesData,
			'currency' => $currency
		]);
	}

	/**
	 * Process language pairs from form input
	 */
	private function processLanguagePairs($languagePairs)
	{
		$processedPairs = [];

		foreach ($languagePairs as $pair) {
			if (empty($pair)) continue;

			$parts = explode('|', $pair);
			if (count($parts) !== 2) continue;

			[$source, $target] = $parts;

			// Get language text and flag from the language codes
			$sourceText = $this->getLanguageText($source);
			$targetText = $this->getLanguageText($target);
			$sourceFlag = $this->getLanguageFlag($source);
			$targetFlag = $this->getLanguageFlag($target);

			$processedPairs[] = [
				'source' => $source,
				'target' => $target,
				'source_text' => $sourceText,
				'target_text' => $targetText,
				'source_flag' => $sourceFlag,
				'target_flag' => $targetFlag,
			];
		}

		return $processedPairs;
	}

	/**
	 * Get language text from language code
	 */
	private function getLanguageText($languageCode)
	{
		$languageVariant = LanguageVariant::where('code', $languageCode)->first();
		return $languageVariant ? $languageVariant->name : $languageCode;
	}

	/**
	 * Get language flag from language code
	 */
	private function getLanguageFlag($languageCode)
	{
		$languageVariant = LanguageVariant::where('code', $languageCode)->first();
		return $languageVariant ? strtolower($languageVariant->country_code) : '';
	}
}
