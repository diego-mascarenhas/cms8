<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class KanbanController extends Controller
{
	public function index()
	{
		return view('kanban.index');
	}
}
