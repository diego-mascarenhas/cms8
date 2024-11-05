<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Source;
use Illuminate\Http\Request;

class ChatController extends Controller
{
	public function index()
	{
		$sources = Source::all();
		
		return view('chat.index', compact('sources'));
	}
}
