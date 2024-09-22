<?php

namespace App\Http\Controllers;

use App\DataTables\ClientDataTable;
use App\Models\Enterprise;
use App\Models\EnterpriseSentimentHistory;
use App\Models\EnterpriseStatus;
use App\Models\EnterpriseSentiment;
use Illuminate\Http\Request;
use Spatie\SimpleExcel\SimpleExcelReader;
use Illuminate\Support\Facades\Storage;

class ClientController extends Controller
{
    public function index(ClientDataTable $dataTable)
    {
        if (!auth()->user()->currentTeam)
        {
            return redirect()->route('error-without-team');
        }
        
        $teamId = auth()->user()->current_team_id;
        
        $data = Enterprise::getContactStats($teamId);
        $data['emotionalStates'] = EnterpriseSentiment::getOptions();

        return $dataTable->render('client.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $enterpriseStatuses = EnterpriseStatus::getOptions(1);

        return view('client.form', compact('enterpriseStatuses'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->except(['id', '_token']);

        $request->validate([
            'name' => 'required|string|min:3|max:25',
            'email' => 'required|email',
        ]);

        $data['team_id'] = auth()->user()->currentTeam->id;
        $data['status_id'] = $request->status_id ?? 1;

        $data['data'] = $data;

        Enterprise::updateOrCreate(
            ['id' => $request->id],
            $data
        );

        return redirect()->route('client-list')->with('success', 'Record saved successfully.');
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
        $row = Enterprise::find($id);

        if (!$row)
        {
            return redirect()->route('client-list')->with('error', 'Client not found.');
        }

        $data = (object) array_merge($row->toArray(), (array) ($row->data ?? new \stdClass()));
        $data->id = $id;

        $enterpriseStatuses = EnterpriseStatus::getOptions(1);

        return view('client.form', compact('data', 'enterpriseStatuses'));
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
        $model = Enterprise::findOrFail($id);

        $model->delete();

        return response()->json(['success' => 'The record has been deleted.'], 200);
    }

    public function updateSentiment(Request $request, string $id)
    {
        $request->validate([
            'sentiment_id' => 'required|exists:enterprise_sentiments,id',
            'notes' => 'nullable|string|max:255',
        ]);
    
        $enterprise = Enterprise::findOrFail($id);
    
        EnterpriseSentimentHistory::create([
            'enterprise_id' => $enterprise->id,
            'sentiment_id' => $request->sentiment_id,
            'notes' => $request->notes,
        ]);
    
        return redirect()->route('client-list')->with('success', 'Sentiment updated successfully.');
    }

    public function importExcel(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        $file = $request->file('excel_file');
        $path = $file->store('temp');
        $fullPath = Storage::path($path);

        $extension = $file->getClientOriginalExtension();
        
        try {
            if ($extension == 'csv') {
                $excel = SimpleExcelReader::create($fullPath, 'csv');
            } else {
                $excel = SimpleExcelReader::create($fullPath);
            }

            $data = [];
            $headers = ['name', 'email', 'teléfono'];
            $rawData = [];

            foreach ($excel->getRows() as $index => $row) {
                $rawData[] = $row;
                
                if ($index === 0 || $index === 1) {
                    continue;
                }
                
                $value = $row['Table 1'] ?? null;
                
                if ($value) {
                    $data[] = $value;
                }
            }

            $groupedData = array_chunk($data, 3);
            $processedData = [];
            foreach ($groupedData as $group) {
                if (count($group) === 3) {
                    $processedData[] = array_combine($headers, $group);
                }
            }

            Storage::delete($path);

            return response()->json([
                'headers' => $headers,
                'data' => $processedData,
                'rawData' => $rawData,
            ]);
        } catch (\Exception $e) {
            Storage::delete($path);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function showImportForm()
    {
        return view('client.import');
    }
}
