<?php

namespace App\Http\Controllers;

use App\DataTables\ServiceDataTable;
use App\Models\Service;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Log;

class ServiceController extends Controller
{
    /**
     * Constructor to authorize service permissions
     */
    public function __construct()
    {
        // Authorize resource actions using ServicePolicy
        $this->authorizeResource(Service::class, 'id');
    }

    public function index(ServiceDataTable $dataTable)
    {
        $teamId = auth()->user()->currentTeam->id;

        // Relevant dates
        $threeMonthsAgo = Carbon::now()->subMonths(3);
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        // Get the latest invoices of clients who have invoices in the last three months
        $invoicesLastThreeMonths = DB::table('invoices')
            ->join('enterprises', 'invoices.enterprise_id', '=', 'enterprises.id')
            ->select('invoices.enterprise_id', DB::raw('MAX(invoices.date) as last_invoice_date'))
            ->where('enterprises.team_id', $teamId)
            ->where('invoices.date', '>=', $threeMonthsAgo)
            ->groupBy('invoices.enterprise_id')
            ->havingRaw('COUNT(invoices.enterprise_id) >= 3');

        // Get the sum of the gross_amount of last month's invoices for those clients
        $totalbuyLastMonth = DB::table('invoices')
            ->join('enterprises', 'invoices.enterprise_id', '=', 'enterprises.id')
            ->joinSub($invoicesLastThreeMonths, 'last_invoices', function ($join)
            {
                $join->on('invoices.enterprise_id', '=', 'last_invoices.enterprise_id');
            })
            ->where('enterprises.team_id', $teamId)
            ->where('invoices.operation', 'buy')
            ->whereBetween('invoices.date', [$lastMonthStart, $lastMonthEnd])
            ->sum('invoices.gross_amount');

        // Project the total monthly amount based on the previous month
        $total_buy = $totalbuyLastMonth;

        // Calculate sales from services (filtered by team)
        $total_sell = Service::whereHas('enterprise', function ($query) use ($teamId)
        {
            $query->where('team_id', $teamId);
        })
            ->where('status', '>=', 4)
            ->whereHas('category', function ($query)
            {
                $query->where('operation', 'sell');
            })
            ->get()
            ->sum('calculated_price');

        // Calculate the total combined buy and sell amounts
        $total_combined = $total_buy + $total_sell;

        // Calculate percentages
        $percentage_buy = $total_combined > 0 ? ($total_buy / $total_combined) * 100 : 0;
        $percentage_sell = $total_combined > 0 ? ($total_sell / $total_combined) * 100 : 0;

        // Calculate the total profit and the profit percentage
        $total_profit = $total_sell - $total_buy;
        $percentage_profit = $total_combined > 0 ? ($total_profit / $total_combined) * 100 : 0;

        // Calculate pending and active services (filtered by team)
        $pending_services = Service::whereHas('enterprise', function ($query) use ($teamId)
        {
            $query->where('team_id', $teamId);
        })->whereIn('status', [2, 3])->count();

        $active_services = Service::whereHas('enterprise', function ($query) use ($teamId)
        {
            $query->where('team_id', $teamId);
        })->where('status', '>=', 4)->count();

        $total_services = $pending_services + $active_services;
        $percentage_pending = $total_services > 0 ? ($pending_services / $total_services) * 100 : 0;

        return $dataTable->render('service.index', compact(
            'total_buy',
            'total_sell',
            'percentage_buy',
            'percentage_sell',
            'total_profit',
            'percentage_profit',
            'pending_services',
            'percentage_pending',
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $enterprise_id = $request->input('enterprise_id');

        return view('service.form', compact('enterprise_id'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try
        {
            $validator = \Validator::make($request->all(), [
                'enterprise_id' => 'required|exists:enterprises,id',
                'category_id' => 'required|exists:categories,id',
                'operation' => 'required|in:buy,sell',
                'description' => 'nullable|string',
                'currency_id' => 'nullable|exists:currencies,id',
                'price' => 'nullable|numeric',
                'discount' => 'nullable|numeric',
                'frequency' => 'nullable|integer|min:1',
                'next_billing' => 'nullable|date',
                'expires_at' => 'nullable|date',
                'status' => 'required|integer|in:1,2,3,4,5,6,7,8',
                'data' => 'nullable|array',
                'responsible_id' => 'nullable|exists:users,id',
            ]);

            if ($validator->fails())
            {
                \Log::error('Service validation failed', [
                    'errors' => $validator->errors()->toArray(),
                    'input' => $request->all(),
                ]);

                return redirect()
                    ->back()
                    ->withErrors($validator)
                    ->withInput()
                    ->with('error', 'Validation failed: '.$validator->errors()->first());
            }

            $input = $request->all();

            // For debugging
            \Log::info('Service data before creation', ['data' => $input]);

            // Format dates
            if (! empty($input['next_billing']))
            {
                $input['next_billing'] = \Carbon\Carbon::parse($input['next_billing']);
            }

            if (! empty($input['expires_at']))
            {
                $input['expires_at'] = \Carbon\Carbon::parse($input['expires_at']);
            }

            // Create the service
            $service = Service::create($input);
            \Log::info('Service created successfully', ['service_id' => $service->id]);

            return redirect()->route('service-list')->with('success', 'Service created successfully');
        } catch (\Exception $e)
        {
            \Log::error('Error creating service', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->all(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Error creating service: '.$e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $service = Service::with(['category', 'client'])->findOrFail($id);

        // Collaborators can only view their assigned services
        $currentUser = auth()->user();
        if ($currentUser && $currentUser->hasRole('collaborator') && $service->responsible_id !== $currentUser->id)
        {
            abort(403);
        }

        // Get service data
        $serviceData = $service->data ? (array) $service->data : [];

        // Status information
        $statusLabels = [
            1 => ['label' => 'Suspended', 'class' => 'bg-label-danger'],
            2 => ['label' => 'To suspend', 'class' => 'bg-label-warning'],
            3 => ['label' => 'To activate', 'class' => 'bg-label-success'],
            4 => ['label' => 'Active', 'class' => 'bg-label-info'],
        ];

        $status = $statusLabels[$service->status] ?? ['label' => 'Unknown', 'class' => 'bg-label-secondary'];

        return view('service.show', compact('service', 'serviceData', 'status'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = Service::find($id);

        if (! $data)
        {
            return redirect()->route('app-service-list')->with('error', 'Service not found.');
        }

        $enterprise_id = $data->enterprise_id;

        return view('service.form', compact('data', 'enterprise_id'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $service = Service::findOrFail($id);

        $request->validate([
            'enterprise_id' => 'required|exists:enterprises,id',
            'category_id' => 'required|exists:categories,id',
            'operation' => 'required|in:buy,sell',
            'description' => 'nullable|string',
            'currency_id' => 'nullable|exists:currencies,id',
            'price' => 'nullable|numeric',
            'discount' => 'nullable|numeric',
            'frequency' => 'nullable|integer|min:1',
            'next_billing' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'status' => 'required|integer|in:1,2,3,4,5,6,7,8',
            'data' => 'nullable|array',
            'responsible_id' => 'nullable|exists:users,id',
        ]);

        $input = $request->all();

        // Format dates
        if (! empty($input['next_billing']))
        {
            $input['next_billing'] = \Carbon\Carbon::parse($input['next_billing']);
        }

        if (! empty($input['expires_at']))
        {
            $input['expires_at'] = \Carbon\Carbon::parse($input['expires_at']);
        }

        // Update the service
        $service->update($input);

        return redirect()->route('service-list')->with('success', 'Service updated successfully');
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

        $services = Service::where('status', '>=', 4)->get();
        $currentDate = Carbon::now();
        $projectionMonths = 12;  // Número de meses para proyectar
        $projectionData = [];
        $totalEarnings = 0;
        $totalExpenses = 0;

        // Inicializar la estructura de datos, omitiendo el mes en curso
        for ($i = 1; $i <= $projectionMonths; $i++)
        {
            $month = $currentDate->copy()->addMonths($i)->format('F Y');
            $projectionData[$month] = [
                'earnings' => 0,
                'expenses' => 0,
            ];
        }

        // Calcular las fechas de facturación y los montos
        foreach ($services as $service)
        {
            $nextBillingDate = Carbon::parse($service->next_billing);

            if ($service->price !== null && $service->price != 0)
            {
                $basePrice = $service->price;
                $discount = $service->discount ?? 0;
                $frequency = $service->frequency;
            } else
            {
                $basePrice = $service->type->price;
                $discount = $service->type->discount ?? 0;
                $frequency = $service->type->frequency;
            }

            // Calcular el precio después del descuento sin dividir por la frecuencia
            $priceAfterDiscount = $basePrice - ($basePrice * ($discount / 100));

            // Asegurarse de que la frecuencia sea un valor válido
            if (is_null($frequency) || $frequency <= 0)
            {
                Log::error('Frecuencia inválida', ['service_id' => $service->id, 'frequency' => $frequency]);

                continue;
            }

            // Asegurarse de que la fecha de próxima facturación es válida
            if (is_null($nextBillingDate) || $nextBillingDate->lessThan($currentDate))
            {
                Log::error('Fecha de próxima facturación inválida', ['service_id' => $service->id, 'next_billing' => $service->next_billing]);

                continue;
            }

            while ($nextBillingDate->lessThanOrEqualTo($currentDate->copy()->addMonths($projectionMonths)))
            {
                $month = $nextBillingDate->format('F Y');

                // Omitir el mes en curso
                if ($month === $currentDate->format('F Y'))
                {
                    $nextBillingDate->addMonths($frequency);

                    continue;
                }

                if (! isset($projectionData[$month]))
                {
                    $projectionData[$month] = [
                        'earnings' => 0,
                        'expenses' => 0,
                    ];
                }

                if ($service->operation == 'sell')
                {
                    $projectionData[$month]['earnings'] += $priceAfterDiscount;
                    $totalEarnings += $priceAfterDiscount;
                } elseif ($service->operation == 'buy')
                {
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
