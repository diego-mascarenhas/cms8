<?php

namespace App\Http\Controllers;

use App\DataTables\AccountDataTable;
use App\Models\Module;
use App\Models\Team;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(AccountDataTable $dataTable)
    {
        return $dataTable->render('account.index');
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
        $team = Team::findOrFail($id);
        $coreModules = Module::where('is_core', true)->get();

        // Group additional modules by their 'group' field
        $additionalModules = Module::where('is_core', false)
            ->orderBy('group')
            ->orderBy('order')
            ->get()
            ->groupBy('group');

        // Define group labels for better UI
        $groupLabels = [
            'billing' => ['name' => 'Billing', 'icon' => 'credit-card', 'description' => 'Invoices, payments, earnings and expenses'],
            'ecommerce' => ['name' => 'E-commerce', 'icon' => 'shopping-cart', 'description' => 'E-commerce module (stores, products, orders)'],
            'infrastructure' => ['name' => 'Infrastructure', 'icon' => 'server', 'description' => 'Infrastructure management (servers, hosting)'],
            'campaigns' => ['name' => 'Campaigns', 'icon' => 'mail-forward', 'description' => 'Email campaigns and marketing automation'],
            'automation' => ['name' => 'Automation', 'icon' => 'robot', 'description' => 'Sales funnel and API integrations'],
            'content' => ['name' => 'Content', 'icon' => 'photo', 'description' => 'Content, multimedia, academy and landing pages'],
            'support' => ['name' => 'Support', 'icon' => 'headset', 'description' => 'Customer support (tickets, mailbox, chat)'],
            'learning' => ['name' => 'Learning & Development', 'icon' => 'book', 'description' => 'Languages, certifications and training'],
            '' => ['name' => 'General Management', 'icon' => 'briefcase', 'description' => 'General management modules'],
        ];

        return view('account.form', compact('team', 'coreModules', 'additionalModules', 'groupLabels'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $team = Team::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'modules' => 'array',
            'modules.*' => 'string|exists:modules,key',
        ]);

        $team->update([
            'name' => $request->name,
        ]);

        // Get all modules (core and additional)
        $allModules = Module::all();

        // Disable all modules first
        foreach ($allModules as $module)
        {
            $team->disableModule($module->key);
        }

        // Enable selected modules
        if ($request->has('modules'))
        {
            foreach ($request->modules as $moduleKey)
            {
                $team->enableModule($moduleKey);
            }
        }

        return redirect()
            ->route('account-management')
            ->with('success', 'Account updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
