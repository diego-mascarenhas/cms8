<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Module;
use App\Models\Software;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SoftwareController extends Controller
{
	public function __construct()
	{
		$this->middleware(['auth:sanctum']);
	}

	/**
	 * Display a listing of the software.
	 */
	public function index(Request $request): JsonResponse
	{
		$this->authorize('viewAny', Software::class);

		$query = Software::with(['category', 'team']);

		// Filter by category if provided
		if ($request->has('category_id'))
		{
			$query->where('category_id', $request->category_id);
		}

		// Search by name if provided
		if ($request->has('search'))
		{
			$query->where('name', 'like', '%'.$request->search.'%');
		}

		// Paginate results
		$perPage = $request->get('per_page', 15);
		$software = $query->paginate($perPage);

		return response()->json([
			'success' => true,
			'data' => $software,
			'message' => 'Software retrieved successfully',
		]);
	}

	/**
	 * Store a newly created software in storage.
	 */
	public function store(Request $request): JsonResponse
	{
		$this->authorize('create', Software::class);

		$validated = $request->validate([
			'name' => ['required', 'string', 'max:255'],
			'category_id' => ['required', 'exists:categories,id'],
		]);

		$validated['team_id'] = auth()->user()->currentTeam->id;

		$software = Software::create($validated);
		$software->load(['category', 'team']);

		return response()->json([
			'success' => true,
			'data' => $software,
			'message' => 'Software created successfully',
		], 201);
	}

	/**
	 * Display the specified software.
	 */
	public function show(Software $software): JsonResponse
	{
		$this->authorize('view', $software);

		$software->load(['category', 'team', 'contacts']);

		return response()->json([
			'success' => true,
			'data' => $software,
			'message' => 'Software retrieved successfully',
		]);
	}

	/**
	 * Update the specified software in storage.
	 */
	public function update(Request $request, Software $software): JsonResponse
	{
		$this->authorize('update', $software);

		$validated = $request->validate([
			'name' => ['sometimes', 'string', 'max:255'],
			'category_id' => ['sometimes', 'exists:categories,id'],
		]);

		$software->update($validated);
		$software->load(['category', 'team']);

		return response()->json([
			'success' => true,
			'data' => $software,
			'message' => 'Software updated successfully',
		]);
	}

	/**
	 * Remove the specified software from storage.
	 */
	public function destroy(Software $software): JsonResponse
	{
		$this->authorize('delete', $software);

		$software->delete();

		return response()->json([
			'success' => true,
			'message' => 'Software deleted successfully',
		]);
	}

	/**
	 * Get all software categories.
	 */
	public function categories(): JsonResponse
	{
		$this->authorize('viewAny', Software::class);

		$softwareModule = Module::where('key', 'softwares')->first();
		$categories = $softwareModule ? Category::where('module_id', $softwareModule->id)
			->withCount('software')
			->orderBy('name')
			->get() : collect();

		return response()->json([
			'success' => true,
			'data' => $categories,
			'message' => 'Software categories retrieved successfully',
		]);
	}

	/**
	 * Get software by category.
	 */
	public function byCategory(Category $category): JsonResponse
	{
		$this->authorize('viewAny', Software::class);

		$software = Software::with(['category', 'team'])
			->where('category_id', $category->id)
			->get();

		return response()->json([
			'success' => true,
			'data' => $software,
			'message' => 'Software filtered by category retrieved successfully',
		]);
	}

	/**
	 * Get software types grouped by categories.
	 */
	public function softwareTypes(): JsonResponse
	{
		$this->authorize('viewAny', Software::class);

		$software = Software::select('name')
			->distinct()
			->get();

		$types = [
			'subtitling' => [],
			'cat_tools' => [],
			'audio_editing' => [],
			'video_editing' => [],
			'office_suite' => [],
			'pdf_editing' => [],
			'design_software' => [],
			'browsers' => [],
			'communication' => [],
			'project_management' => [],
			'other' => [],
		];

		foreach ($software as $soft)
		{
			$name = strtolower($soft->name);

			if (str_contains($name, 'subtitle') || str_contains($name, 'aegisub') || str_contains($name, 'eztitles'))
			{
				$types['subtitling'][] = $soft->name;
			} elseif (str_contains($name, 'trados') || str_contains($name, 'memoq') || str_contains($name, 'wordfast') ||
					  str_contains($name, 'cat') || str_contains($name, 'omegat') || str_contains($name, 'smartcat'))
			{
				$types['cat_tools'][] = $soft->name;
			} elseif (str_contains($name, 'pro tools') || str_contains($name, 'audition') || str_contains($name, 'logic') ||
					  str_contains($name, 'cubase') || str_contains($name, 'reaper') || str_contains($name, 'audacity'))
			{
				$types['audio_editing'][] = $soft->name;
			} elseif (str_contains($name, 'premiere') || str_contains($name, 'final cut') || str_contains($name, 'davinci') ||
					  str_contains($name, 'avid') || str_contains($name, 'vegas') || str_contains($name, 'after effects'))
			{
				$types['video_editing'][] = $soft->name;
			} elseif (str_contains($name, 'word') || str_contains($name, 'excel') || str_contains($name, 'powerpoint') ||
					  str_contains($name, 'office') || str_contains($name, 'google docs') || str_contains($name, 'sheets'))
			{
				$types['office_suite'][] = $soft->name;
			} elseif (str_contains($name, 'acrobat') || str_contains($name, 'pdf') || str_contains($name, 'foxit'))
			{
				$types['pdf_editing'][] = $soft->name;
			} elseif (str_contains($name, 'photoshop') || str_contains($name, 'illustrator') || str_contains($name, 'indesign') ||
					  str_contains($name, 'canva') || str_contains($name, 'figma') || str_contains($name, 'sketch'))
			{
				$types['design_software'][] = $soft->name;
			} elseif (str_contains($name, 'chrome') || str_contains($name, 'firefox') || str_contains($name, 'safari') ||
					  str_contains($name, 'edge') || str_contains($name, 'browser'))
			{
				$types['browsers'][] = $soft->name;
			} elseif (str_contains($name, 'slack') || str_contains($name, 'teams') || str_contains($name, 'zoom') ||
					  str_contains($name, 'skype') || str_contains($name, 'discord') || str_contains($name, 'whatsapp'))
			{
				$types['communication'][] = $soft->name;
			} elseif (str_contains($name, 'trello') || str_contains($name, 'asana') || str_contains($name, 'jira') ||
					  str_contains($name, 'notion') || str_contains($name, 'monday'))
			{
				$types['project_management'][] = $soft->name;
			} else
			{
				$types['other'][] = $soft->name;
			}
		}

		// Remove empty categories and sort
		$types = array_filter($types, function ($category)
		{
			return ! empty($category);
		});

		foreach ($types as $key => $category)
		{
			sort($types[$key]);
		}

		return response()->json([
			'success' => true,
			'data' => $types,
			'message' => 'Software types retrieved successfully',
		]);
	}
}
