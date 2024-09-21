<?php

namespace App\Http\Controllers;

use App\DataTables\ClientDataTable;
use App\Models\Enterprise;
use App\Models\EnterpriseSentimentHistory;
use App\Models\EnterpriseStatus;
use App\Models\EnterpriseSentiment;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(ClientDataTable $dataTable)
    {
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
        ]);

        $data['team_id'] = auth()->user()->currentTeam->id;

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
        $page = Enterprise::find($id);

        if (!$page)
        {
            return redirect()->route('client.index')->with('error', 'Page not found.');
        }

        return view('page.show', compact('page'));
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
        else
        {
            $data = (object) ($row->data ?? []);

            $data->id = $id;
        }

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
}
