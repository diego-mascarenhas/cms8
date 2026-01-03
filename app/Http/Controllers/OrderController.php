<?php

namespace App\Http\Controllers;

use App\DataTables\OrderDataTable;
use App\Models\Order;

class OrderController extends Controller
{
    /**
     * Constructor to authorize order permissions
     */
    public function __construct()
    {
        // Note: Manual authorization in each method due to non-standard route parameter names
        // Laravel's authorizeResource() expects {order} parameter, but routes use {id}
    }

    /**
     * Display a listing of the resource.
     */
    public function index(OrderDataTable $dataTable)
    {
        $this->authorize('viewAny', Order::class);

        if (! auth()->user()->currentTeam)
        {
            return redirect()->route('error-without-team');
        }

        return $dataTable->render('order.list');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $order = Order::with(['contact', 'currency', 'team'])->findOrFail($id);

        $this->authorize('view', $order);

        return view('order.show', compact('order'));
    }
}
