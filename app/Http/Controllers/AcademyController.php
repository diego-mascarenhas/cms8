<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

class AcademyController extends Controller
{
	public function index()
	{
		return view('academy.index');
	}

	public function show($id)
	{
		return view('academy.show');
	}
}
