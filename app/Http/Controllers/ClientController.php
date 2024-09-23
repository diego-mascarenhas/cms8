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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

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
        $data['enterpriseStatuses'] = EnterpriseStatus::getOptions(1);


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

            $rawData = [];
            $processedData = [];
            $updatedCount = 0;
            $duplicateCount = 0;
            $headers = null;

            foreach ($excel->getRows() as $index => $row) {
                $rawData[] = $row;

                if ($index === 0) {
                    if ($this->isHeaderRow($row)) {
                        $headers = array_map([$this, 'normalizeHeader'], array_keys($row));
                        continue; // Skip header row
                    }
                }

                $values = array_values(array_filter($row));

                if (count($values) >= 2) { // At least name and email
                    $client = $this->detectFields($values);
                    $client['team_id'] = Auth::user()->currentTeam->id;

                    if ($headers) {
                        $additionalData = array_slice($values, 3);
                        $additionalDataAssoc = [];

                        // Ensure both arrays have the same length
                        for ($i = 0; $i < count($additionalData); $i++) {
                            if (isset($headers[$i + 3])) {
                                $additionalDataAssoc[$headers[$i + 3]] = $additionalData[$i];
                            }
                        }

                        $client['data'] = !empty($additionalDataAssoc) ? $additionalDataAssoc : null;
                    } else {
                        $additionalData = array_slice($values, 3);
                        $client['data'] = !empty($additionalData) ? $additionalData : null;
                    }

                    $validator = Validator::make($client, [
                        'name' => 'required|string',
                        'email' => 'required|email',
                        'phone' => 'nullable',
                    ]);

                    if ($validator->fails()) {
                        continue; // Skip this row if validation fails
                    }

                    $existingClient = Enterprise::where('email', $client['email'])
                                                ->where('team_id', $client['team_id'])
                                                ->first();

                    if ($existingClient) {
                        $existingClient->update($client);
                        $updatedCount++;
                    } else {
                        Enterprise::create($client);
                        $processedData[] = $client;
                    }
                }
            }

            Storage::delete($path);

            return response()->json([
                'message' => 'Importación completada con éxito',
                'processed' => count($processedData),
                'updated' => $updatedCount,
                'duplicates' => $duplicateCount,
                'data' => $processedData,
                'rawData' => $rawData,
            ]);
        } catch (\Exception $e) {
            Storage::delete($path);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function detectFields($values)
    {
        $client = [
            'name' => null,
            'email' => null,
            'phone' => null,
        ];

        foreach ($values as $value) {
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $client['email'] = $value;
            } elseif (preg_match('/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/', $value)) {
                $client['phone'] = $value;
            } else {
                $client['name'] = $value;
            }

            if ($client['name'] && $client['email']) {
                break;
            }
        }

        return $client;
    }

    private function isHeaderRow($row)
    {
        foreach ($row as $value) {
            if (!is_string($value)) {
                return false;
            }
        }
        return true;
    }

    private function normalizeHeader($header)
    {
        $header = strtolower($header);
        $header = iconv('UTF-8', 'ASCII//TRANSLIT', $header);
        $header = preg_replace('/[^a-z0-9_]/', '_', $header);
        return $header;
    }

    public function showImportForm()
    {
        return view('client.import');
    }
}
