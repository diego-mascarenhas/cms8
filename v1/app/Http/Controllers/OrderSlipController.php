<?php

namespace App\Http\Controllers;

use App\DataTables\OrderSlipDataTable;

class OrderSlipController extends Controller
{
	public function index(OrderSlipDataTable $dataTable)
	{
		return $dataTable->render('orderslip.index');
	}
}