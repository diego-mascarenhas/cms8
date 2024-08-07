<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Request;
use Dotlogics\Grapesjs\App\Traits\EditorTrait;

class PageController extends Controller
{
    use EditorTrait;

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

        if (!$page) {
            return redirect()->route('page.index')->with('error', 'Page not found.');
        }

        return view('page.show', compact('page'));
    }

    public function home()
    {
        $page = Page::findOrFail(1);

        return view('page.show', compact('page'));
    }
}
