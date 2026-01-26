<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ContentFieldConfig;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContentFieldConfigController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', ContentFieldConfig::class);

        $team = Auth::user()->currentTeam;
        $sectionId = $request->get('section_id');

        $contentsModuleId = Module::where('key', 'contents')->value('id');
        $sectionCategories = Category::where('team_id', $team->id)
            ->where('module_id', $contentsModuleId)
            ->where('status', true)
            ->orderBy('name')
            ->get();

        $query = ContentFieldConfig::where('team_id', $team->id)
            ->with('sectionCategory');

        if ($sectionId)
        {
            $query->where('section_category_id', $sectionId);
        }

        $fieldConfigs = $query->orderBy('section_category_id')
            ->orderBy('order')
            ->get();

        return view('content-field-configs.index', compact('fieldConfigs', 'sectionCategories', 'sectionId', 'team'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', ContentFieldConfig::class);

        $team = Auth::user()->currentTeam;
        $sectionId = $request->get('section_id');

        $contentsModuleId = Module::where('key', 'contents')->value('id');
        $sectionCategories = Category::where('team_id', $team->id)
            ->where('module_id', $contentsModuleId)
            ->where('status', true)
            ->orderBy('name')
            ->get();

        $fieldTypes = [
            'text' => __('app.Text'),
            'textarea' => __('app.Textarea'),
            'number' => __('app.Number'),
            'select' => __('app.Select'),
            'checkbox' => __('app.Checkbox'),
            'date' => __('app.Date'),
            'datetime' => __('app.DateTime'),
            'url' => __('app.URL'),
            'email' => __('app.Email'),
        ];

        return view('content-field-configs.form', compact('sectionCategories', 'fieldTypes', 'sectionId', 'team'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', ContentFieldConfig::class);

        $team = Auth::user()->currentTeam;

        $contentsModuleId = Module::where('key', 'contents')->value('id');

        $request->validate([
            'section_category_id' => [
                'required',
                \Illuminate\Validation\Rule::exists('categories', 'id')->where(function ($query) use ($contentsModuleId)
                {
                    $query->where('module_id', $contentsModuleId);
                }),
            ],
            'field_key' => ['required', 'string', 'max:255'],
            'field_type' => ['required', 'string', 'max:50'],
            'field_label' => ['required', 'string', 'max:255'],
            'field_options' => ['nullable', 'array'],
            'is_active' => ['boolean'],
            'order' => ['nullable', 'integer', 'min:0'],
            'required' => ['boolean'],
        ]);

        $data = $request->all();
        $data['team_id'] = $team->id;

        ContentFieldConfig::create($data);

        return redirect()
            ->route('content-field-configs.index', ['section_id' => $request->section_category_id])
            ->with('success', __('app.Field configuration created successfully.'));
    }

    public function show(ContentFieldConfig $contentFieldConfig)
    {
        $this->authorize('view', $contentFieldConfig);

        $contentFieldConfig->load('sectionCategory');

        return view('content-field-configs.show', compact('contentFieldConfig'));
    }

    public function edit(ContentFieldConfig $contentFieldConfig)
    {
        $this->authorize('update', $contentFieldConfig);

        $team = Auth::user()->currentTeam;

        $contentsModuleId = Module::where('key', 'contents')->value('id');
        $sectionCategories = Category::where('team_id', $team->id)
            ->where('module_id', $contentsModuleId)
            ->where('status', true)
            ->orderBy('name')
            ->get();

        $fieldTypes = [
            'text' => __('app.Text'),
            'textarea' => __('app.Textarea'),
            'number' => __('app.Number'),
            'select' => __('app.Select'),
            'checkbox' => __('app.Checkbox'),
            'date' => __('app.Date'),
            'datetime' => __('app.DateTime'),
            'url' => __('app.URL'),
            'email' => __('app.Email'),
        ];

        return view('content-field-configs.form', compact('contentFieldConfig', 'sectionCategories', 'fieldTypes', 'team'));
    }

    public function update(Request $request, ContentFieldConfig $contentFieldConfig)
    {
        $this->authorize('update', $contentFieldConfig);

        $request->validate([
            'section_category_id' => ['required', 'exists:categories,id'],
            'field_key' => ['required', 'string', 'max:255'],
            'field_type' => ['required', 'string', 'max:50'],
            'field_label' => ['required', 'string', 'max:255'],
            'field_options' => ['nullable', 'array'],
            'is_active' => ['boolean'],
            'order' => ['nullable', 'integer', 'min:0'],
            'required' => ['boolean'],
        ]);

        $contentFieldConfig->update($request->all());

        return redirect()
            ->route('content-field-configs.index', ['section_id' => $request->section_category_id])
            ->with('success', __('app.Field configuration updated successfully.'));
    }

    public function destroy(ContentFieldConfig $contentFieldConfig)
    {
        $this->authorize('delete', $contentFieldConfig);

        $contentFieldConfig->delete();

        return response()->json([
            'success' => __('app.Field configuration deleted successfully.'),
        ]);
    }
}
