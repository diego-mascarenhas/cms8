<?php

namespace App\Http\Controllers;

use App\DataTables\ServiceDataTable;
use App\Models\Service;
use Illuminate\Http\Request;
use stdClass;

class ServiceController extends Controller
{
    public function index(ServiceDataTable $dataTable)
    {
        $total_buy = Service::calculateTotal(4, 'Buy');
        $total_sell = Service::calculateTotal(4, 'Sell');
        $total_combined = $total_buy + $total_sell;

        $percentage_buy = $total_combined > 0 ? ($total_buy / $total_combined) * 100 : 0;
        $percentage_sell = $total_combined > 0 ? ($total_sell / $total_combined) * 100 : 0;

        $total_profit = $total_sell - $total_buy;
        $percentage_profit = $total_combined > 0 ? ($total_profit / $total_combined) * 100 : 0;

        $pending_services = Service::whereIn('status', [2, 3])->count();
        $active_services = Service::where('status', 4)->count();
        $total_services = $pending_services + $active_services;
        $percentage_pending = $total_services > 0 ? ($pending_services / $total_services) * 100 : 0;

        return $dataTable->render('service.index', compact(
            'total_buy', 'total_sell', 'percentage_buy', 'percentage_sell', 'total_profit', 'percentage_profit', 'pending_services', 'percentage_pending'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('service.form');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->except(['id', '_token']);

        $request->validate([
            'name' => 'required|string|min:3|max:25',
        ]);

        $data['status'] = $request->has('status') ? 1 : 0;

        Service::updateOrCreate(
            ['id' => $request->id],
            [
                'name' => $data['name'],
                'status' => $data['status'],
            ]
        );

        return redirect()->route('app-service-list')->with('success', 'Record saved successfully.');
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
        $data = Service::find($id);

        if (!$data)
        {
            return redirect()->route('app-service-list')->with('error', 'Service not found.');
        }

        return view('service.form', compact('data'));
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
        $model = Service::findOrFail($id);

        $model->delete();

        return response()->json(['success' => 'The record has been deleted.'], 200);
    }
}
