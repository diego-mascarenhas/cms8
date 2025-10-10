<?php

namespace App\Http\Controllers;

class KanbanController extends Controller
{
	/**
	 * Redirect to the Kanban board view
	 *
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public function index()
	{
		// Redirect to tasks list with Kanban view
		return redirect()->route('task.index', ['view' => 'kanban']);
	}
}
