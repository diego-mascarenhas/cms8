<?php

namespace App\Http\Controllers;

use App\DataTables\PaymentDataTable;
use App\Models\Payment;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(PaymentDataTable $dataTable)
    {
        return $dataTable->render('payments.index');
    }

    public function show($id): View
    {
        $payment = Payment::with(['enterprise', 'invoice', 'account', 'type'])->findOrFail($id);

        return view('payments.show', compact('payment'));
    }
}
