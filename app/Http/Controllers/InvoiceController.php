<?php

namespace App\Http\Controllers;

use App\DataTables\InvoiceDataTable;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(InvoiceDataTable $dataTable)
    {
        // Obtener tipos de cambio actuales
        $exchangeRates = [
            'USD_ARS' => ExchangeRate::getLatestRate('USD', 'ARS'),
            'USD_EUR' => ExchangeRate::getLatestRate('USD', 'EUR'),
            'ARS_EUR' => ExchangeRate::getLatestRate('ARS', 'EUR'),
        ];

        // Obtener fecha de última actualización
        $lastUpdate = ExchangeRate::latest('fetched_at')->first();

        return $dataTable->render('invoices.index', compact('exchangeRates', 'lastUpdate'));
    }

    public function show($id): View
    {
        $invoice = Invoice::with(['enterprise', 'items.category', 'type'])->findOrFail($id);

        return view('invoices.show', compact('invoice'));
    }
}
