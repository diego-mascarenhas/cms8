<?php

namespace App\Http\Controllers;

use App\DataTables\ServiceDataTable;
use App\Models\Service;
use Illuminate\Http\Request;
use stdClass;
use Carbon\Carbon;
use Log;

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

    public function projectBilling()
    {
        Log::info('Iniciando proyección de facturación');

        $services = Service::where('status', 4)->get();
        $currentDate = Carbon::now();
        $projectionMonths = 12; // Número de meses para proyectar
        $projectionData = [];
        $totalEarnings = 0;
        $totalExpenses = 0;

        // Inicializar la estructura de datos, omitiendo el mes en curso
        for ($i = 1; $i <= $projectionMonths; $i++) {
            $month = $currentDate->copy()->addMonths($i)->format('F Y');
            $projectionData[$month] = [
                'earnings' => 0,
                'expenses' => 0,
            ];
        }

        // Calcular las fechas de facturación y los montos
        foreach ($services as $service) {
            $nextBillingDate = Carbon::parse($service->next_billing);

            if ($service->price !== null && $service->price != 0) {
                $basePrice = $service->price;
                $discount = $service->discount ?? 0;
                $frequency = $service->frequency;
            } else {
                $basePrice = $service->type->price;
                $discount = $service->type->discount ?? 0;
                $frequency = $service->type->frequency;
            }

            // Calcular el precio después del descuento sin dividir por la frecuencia
            $priceAfterDiscount = $basePrice - ($basePrice * ($discount / 100));

            // Asegurarse de que la frecuencia sea un valor válido
            if (is_null($frequency) || $frequency <= 0) {
                Log::error('Frecuencia inválida', ['service_id' => $service->id, 'frequency' => $frequency]);
                continue;
            }

            // Asegurarse de que la fecha de próxima facturación es válida
            if (is_null($nextBillingDate) || $nextBillingDate->lessThan($currentDate)) {
                Log::error('Fecha de próxima facturación inválida', ['service_id' => $service->id, 'next_billing' => $service->next_billing]);
                continue;
            }

            while ($nextBillingDate->lessThanOrEqualTo($currentDate->copy()->addMonths($projectionMonths))) {
                $month = $nextBillingDate->format('F Y');

                // Omitir el mes en curso
                if ($month === $currentDate->format('F Y')) {
                    $nextBillingDate->addMonths($frequency);
                    continue;
                }

                if (!isset($projectionData[$month])) {
                    $projectionData[$month] = [
                        'earnings' => 0,
                        'expenses' => 0,
                    ];
                }

                if ($service->operation == 'Sell') {
                    $projectionData[$month]['earnings'] += $priceAfterDiscount;
                    $totalEarnings += $priceAfterDiscount;
                } elseif ($service->operation == 'Buy') {
                    $projectionData[$month]['expenses'] += $priceAfterDiscount;
                    $totalExpenses += $priceAfterDiscount;
                }

                $nextBillingDate->addMonths($frequency);
            }
        }

        Log::info('Proyección completada', ['projection_data' => $projectionData]);

        return view('service.projection', compact('projectionData', 'totalEarnings', 'totalExpenses'));
    }
}
