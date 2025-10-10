<?php

namespace App\Http\Controllers;

use App\DataTables\InvoiceDataTable;
use App\Models\Invoice;
use Illuminate\View\View;

class InvoiceController extends Controller
{
	public function index(InvoiceDataTable $dataTable)
	{
		return $dataTable->render('invoices.index');
	}

	public function show($id): View
	{
		$invoice = Invoice::with(['enterprise', 'items.category', 'type'])->findOrFail($id);

		return view('invoices.show', compact('invoice'));
	}
}
