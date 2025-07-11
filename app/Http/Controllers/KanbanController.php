<?php

namespace App\Http\Controllers;

class KanbanController extends Controller
{
    public function index()
    {
        return view('kanban.index');
    }
}
