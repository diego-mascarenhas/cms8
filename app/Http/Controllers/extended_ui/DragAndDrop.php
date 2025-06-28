<?php

namespace App\Http\Controllers\extended_ui;

use App\Http\Controllers\Controller;

class DragAndDrop extends Controller
{
    public function index()
    {
        return view('content.extended-ui.extended-ui-drag-and-drop');
    }
}
