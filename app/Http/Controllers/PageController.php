<?php

namespace App\Http\Controllers;

use App\Grapesjs\TeamLandingEditable;
use App\Models\Page;
use Dotlogics\Grapesjs\App\Traits\EditorTrait;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PageController extends Controller
{
    use EditorTrait;

    /**
     * GrapesJS editor for the current team's landing HTML (stored in team_settings JSON).
     */
    public function teamLandingEditor(Request $request): View
    {
        $user = $request->user();
        if ($user === null || $user->currentTeam === null)
        {
            abort(403);
        }

        $editable = TeamLandingEditable::fromTeam($user->currentTeam);

        return $this->show_gjs_editor($request, $editable);
    }

    /**
     * Persist GrapesJS payload into {@see TeamLandingEditable::SETTING_KEY}.
     */
    public function teamLandingEditorStore(Request $request): Response
    {
        $user = $request->user();
        if ($user === null || $user->currentTeam === null)
        {
            abort(403);
        }

        $gjsData = [
            'components' => $request->input('laravel-grapesjs-components'),
            'styles' => $request->input('laravel-grapesjs-styles'),
            'css' => $request->input('laravel-grapesjs-css'),
            'html' => $request->input('laravel-grapesjs-html'),
        ];

        $user->currentTeam->setSetting(TeamLandingEditable::SETTING_KEY, $gjsData, [
            'type' => 'json',
            'group' => 'website',
            'is_encrypted' => false,
        ]);

        return response()->noContent(200);
    }

    public function editor(Request $request, Page $page)
    {
        return $this->show_gjs_editor($request, $page);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $page = Page::find($id);

        if (! $page)
        {
            return redirect()->route('page.index')->with('error', 'Page not found.');
        }

        return view('page.show', compact('page'));
    }

    public function home()
    {
        $page = Page::findOrFail(1);

        return view('page.home', compact('page'));
    }
}
