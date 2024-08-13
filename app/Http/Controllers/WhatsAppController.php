<?php

namespace App\Http\Controllers;

use App\DataTables\WhastappDataTable;

class WhatsAppController extends Controller
{
	public function index(WhastappDataTable $dataTable)
	{
		return $dataTable->render('whatsapp.index');
	}
}
