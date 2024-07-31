<?php

namespace App\Http\Controllers;

use App\DataTables\PaymentDataTable;
use App\Models\Payment;
use App\Models\PaymentAccount;
use Illuminate\Http\Request;
use stdClass;
use Carbon\Carbon;
use Log;

class PaymentController extends Controller
{
    public function index(PaymentDataTable $dataTable)
    {
        $accounts = PaymentAccount::all()->map(function ($account)
        {
            $income = $account->payments()
                ->approvedStatus()
                ->where('transaction_type', 'I')
                ->sum('amount');

            $expense = $account->payments()
                ->approvedStatus()
                ->where('transaction_type', 'E')
                ->sum('amount');

            $balance = $income - $expense;

            return [
                'name' => $account->name,
                'balance' => $balance,
                'currency_code' => $account->currency ? $account->currency->code : 'N/A',
            ];
        });

        return $dataTable->render('payment.index', compact('accounts'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $model = Payment::findOrFail($id);

        $model->delete();

        return response()->json(['success' => 'The record has been deleted.'], 200);
    }
}
