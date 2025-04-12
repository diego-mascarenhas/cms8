<?php

namespace App\Http\Controllers;

use App\DataTables\DomainDataTable;
use App\Models\Server;
use App\Traits\TracksContactActions;

class HostingController extends Controller
{
	use TracksContactActions;

	public function index(DomainDataTable $dataTable)
	{
		$data['servers'] = Server::all();

		return $dataTable->render('hosting.index', $data);
	}
}
