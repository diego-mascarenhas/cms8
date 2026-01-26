<?php

namespace App\Http\Controllers;

use App\DataTables\ContentDataTable;
use App\Http\Requests\Content\StoreContentRequest;
use App\Http\Requests\Content\UpdateContentRequest;
use App\Models\Category;
use App\Models\Content;
use App\Models\Module;
use App\Models\Multimedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContentController extends Controller
{
    public function index(ContentDataTable $dataTable, Request $request)
    {
        $this->authorize('viewAny', Content::class);

        $team = Auth::user()->currentTeam;
        $sectionId = $request->get('section_id');
        $categoryId = $request->get('category_id');

        $contentsModuleId = Module::where('key', 'contents')->value('id');
        $sectionCategories = Category::where('team_id', $team->id)
            ->where('module_id', $contentsModuleId)
            ->where('status', true)
            ->orderBy('name')
            ->get();

        if ($request->ajax())
        {
            return $dataTable->ajax();
        }

        return $dataTable->render('contents.index', compact('dataTable', 'sectionCategories', 'sectionId', 'team'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Content::class);

        $team = Auth::user()->currentTeam;
        $sectionId = $request->get('section_id');

        $contentsModuleId = Module::where('key', 'contents')->value('id');
        $sectionCategories = Category::where('team_id', $team->id)
            ->where('module_id', $contentsModuleId)
            ->where('status', true)
            ->orderBy('name')
            ->get();

        $selectedSection = $sectionId ? Category::find($sectionId) : null;
        $fieldConfigs = $selectedSection ? $selectedSection->contentFieldConfigs()->active()->ordered()->get() : collect();

        return view('contents.form', compact('sectionCategories', 'selectedSection', 'fieldConfigs', 'team'));
    }

    public function store(StoreContentRequest $request)
    {
        $this->authorize('create', Content::class);

        $team = Auth::user()->currentTeam;
        $data = $request->validated();

        // Prepare translatable fields
        $locale = app()->getLocale();
        $translatableFields = ['title', 'subtitle', 'url', 'content1', 'content2', 'content3', 'content4', 'content5', 'seo_title', 'seo_keywords', 'seo_description'];

        foreach ($translatableFields as $field)
        {
            if ($request->has($field))
            {
                $data[$field] = [$locale => $request->input($field)];
            }
        }

        // Extract data fields (additional fields from config)
        $dataFields = [];
        $section = Category::findOrFail($data['section_category_id']);
        $fieldConfigs = $section->contentFieldConfigs()->active()->get();

        foreach ($fieldConfigs as $config)
        {
            $key = $config->field_key;
            if ($request->has("data.{$key}"))
            {
                $dataFields[$key] = $request->input("data.{$key}");
            }
        }

        if (! empty($dataFields))
        {
            $data['data'] = $dataFields;
        }

        $data['team_id'] = $team->id;
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        $content = Content::create($data);

        // Sync multimedia
        if ($request->has('multimedia'))
        {
            $this->syncMultimedia($content, $request->input('multimedia', []));
        }

        return redirect()
            ->route('contents.index', ['section_id' => $content->section_category_id])
            ->with('success', __('app.Content created successfully.'));
    }

    public function show(Content $content)
    {
        $this->authorize('view', $content);

        $content->load(['sectionCategory', 'category', 'multimedia', 'creator', 'updater']);

        return view('contents.show', compact('content'));
    }

    public function edit(Content $content)
    {
        $this->authorize('update', $content);

        $team = Auth::user()->currentTeam;

        $contentsModuleId = Module::where('key', 'contents')->value('id');
        $sectionCategories = Category::where('team_id', $team->id)
            ->where('module_id', $contentsModuleId)
            ->where('status', true)
            ->orderBy('name')
            ->get();

        $fieldConfigs = $content->sectionCategory->contentFieldConfigs()->active()->ordered()->get();
        $selectedMultimedia = $content->multimedia->pluck('id')->toArray();

        return view('contents.form', compact('content', 'sectionCategories', 'fieldConfigs', 'selectedMultimedia', 'team'));
    }

    public function update(UpdateContentRequest $request, Content $content)
    {
        $this->authorize('update', $content);

        $data = $request->validated();

        // Prepare translatable fields
        $locale = app()->getLocale();
        $translatableFields = ['title', 'subtitle', 'url', 'content1', 'content2', 'content3', 'content4', 'content5', 'seo_title', 'seo_keywords', 'seo_description'];

        foreach ($translatableFields as $field)
        {
            if ($request->has($field))
            {
                $current = $content->$field ?? [];
                if (! is_array($current))
                {
                    $current = [];
                }
                $current[$locale] = $request->input($field);
                $data[$field] = $current;
            }
        }

        // Extract data fields (additional fields from config)
        $dataFields = $content->data ?? [];
        $sectionId = $data['section_category_id'] ?? $content->section_category_id;
        $section = Category::findOrFail($sectionId);
        $fieldConfigs = $section->contentFieldConfigs()->active()->get();

        foreach ($fieldConfigs as $config)
        {
            $key = $config->field_key;
            if ($request->has("data.{$key}"))
            {
                $dataFields[$key] = $request->input("data.{$key}");
            }
        }

        if (! empty($dataFields))
        {
            $data['data'] = $dataFields;
        }

        $data['updated_by'] = Auth::id();

        $content->update($data);

        // Sync multimedia
        if ($request->has('multimedia') && is_array($request->input('multimedia')))
        {
            $multimediaIds = array_filter($request->input('multimedia', []));
            if (! empty($multimediaIds))
            {
                $this->syncMultimedia($content, $multimediaIds);
            } else
            {
                $content->multimedia()->detach();
            }
        }

        return redirect()
            ->route('contents.index', ['section_id' => $content->section_category_id])
            ->with('success', __('app.Content updated successfully.'));
    }

    public function destroy(Content $content)
    {
        $this->authorize('delete', $content);

        $content->delete();

        return response()->json([
            'success' => __('app.Content deleted successfully.'),
        ]);
    }

    public function updateOrder(Request $request)
    {
        $this->authorize('update', Content::class);

        $request->validate([
            'contents' => 'required|array',
            'contents.*.id' => 'required|exists:contents,id',
            'contents.*.order' => 'required|integer|min:0',
        ]);

        $team = Auth::user()->currentTeam;

        foreach ($request->contents as $item)
        {
            Content::where('team_id', $team->id)
                ->where('id', $item['id'])
                ->update(['order' => $item['order']]);
        }

        return response()->json(['success' => __('app.Order updated successfully.')], 200);
    }

    private function syncMultimedia(Content $content, array $multimediaData): void
    {
        $content->multimedia()->detach();

        if (empty($multimediaData))
        {
            return;
        }

        // Handle array of IDs or array of objects
        foreach ($multimediaData as $index => $item)
        {
            $multimediaId = is_array($item) ? ($item['id'] ?? null) : $item;

            if ($multimediaId)
            {
                $content->multimedia()->attach($multimediaId, [
                    'language' => (is_array($item) && isset($item['language'])) ? $item['language'] : app()->getLocale(),
                    'type' => (is_array($item) && isset($item['type'])) ? $item['type'] : 1,
                    'order' => $index,
                ]);
            }
        }
    }
}
