<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ModuleList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:list
							{--team= : Show modules for specific team ID}
							{--available : Show only available (not installed) modules}
							{--installed : Show only installed modules}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all available modules and their installation status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $teamId = $this->option('team');
        $showAvailable = $this->option('available');
        $showInstalled = $this->option('installed');

        $this->info('═══════════════════════════════════════════════');
        $this->info('📦 HUMANO MODULES');
        $this->info('═══════════════════════════════════════════════');
        $this->newLine();

        if ($teamId)
        {
            $this->showModulesForTeam($teamId, $showAvailable, $showInstalled);
        } else
        {
            $this->showAllModules($showAvailable, $showInstalled);
        }

        $this->newLine();
        $this->info('💡 Tips:');
        $this->line('   • Install a module: php artisan module:install {module_key}');
        $this->line('   • Install for specific team: php artisan module:install {module_key} --team={team_id}');
        $this->line('   • List team modules: php artisan module:list --team={team_id}');

        return 0;
    }

    /**
     * Show modules for all teams
     */
    protected function showAllModules($showAvailable, $showInstalled)
    {
        $modules = Module::all();

        // Group by type
        $coreModules = $modules->where('is_core', true);
        $addonModules = $modules->where('is_core', false);

        // Core modules
        $this->info('🔹 CORE MODULES');
        $this->displayModules($coreModules, null, $showAvailable, $showInstalled);

        $this->newLine();

        // Addon modules
        $this->info('🔸 ADD-ON MODULES');
        $this->displayModules($addonModules, null, $showAvailable, $showInstalled);
    }

    /**
     * Show modules for specific team
     */
    protected function showModulesForTeam($teamId, $showAvailable, $showInstalled)
    {
        $team = Team::find($teamId);

        if (! $team)
        {
            $this->error("❌ Team with ID {$teamId} not found");

            return;
        }

        $this->info("Team: {$team->name} (ID: {$teamId})");
        $this->newLine();

        $modules = Module::all();

        // Core modules
        $this->info('🔹 CORE MODULES');
        $coreModules = $modules->where('is_core', true);
        $this->displayModules($coreModules, $team, $showAvailable, $showInstalled);

        $this->newLine();

        // Addon modules
        $this->info('🔸 ADD-ON MODULES');
        $addonModules = $modules->where('is_core', false);
        $this->displayModules($addonModules, $team, $showAvailable, $showInstalled);
    }

    /**
     * Display modules in table format
     */
    protected function displayModules($modules, $team = null, $showAvailable = false, $showInstalled = false)
    {
        $data = [];

        foreach ($modules as $module)
        {
            $isInstalled = $team ? $team->hasModule($module->key) : $this->isInstalledGlobally($module);

            // Filter based on flags
            if ($showAvailable && $isInstalled)
            {
                continue;
            }
            if ($showInstalled && ! $isInstalled)
            {
                continue;
            }

            $status = $isInstalled ? '✅ Installed' : '⬜ Available';
            $hasTables = $this->hasModuleTables($module->key) ? '✓' : '✗';
            $teamsCount = $this->getTeamsCount($module);

            $data[] = [
                'key' => $module->key,
                'name' => $module->name,
                'status' => $status,
                'tables' => $hasTables,
                'teams' => $teamsCount,
                'description' => Str::limit($module->description ?? '', 40),
            ];
        }

        if (empty($data))
        {
            $this->line('   No modules to display');

            return;
        }

        $this->table(
            ['Key', 'Name', 'Status', 'Tables', 'Teams', 'Description'],
            $data,
        );
    }

    /**
     * Check if module is installed globally (at least one team has it)
     */
    protected function isInstalledGlobally(Module $module)
    {
        return DB::table('module_team')
            ->where('module_id', $module->id)
            ->where('status', 1)
            ->exists();
    }

    /**
     * Check if module has database tables
     */
    protected function hasModuleTables($moduleKey)
    {
        $tableMap = [
            'billing' => ['invoices', 'payments'],
            'ecommerce' => ['orders', 'products'],
            'tickets' => ['tickets'],
            'academy' => ['courses'],
            'mailbox' => ['mailbox_messages'],
            'chat' => ['chat_messages'],
            'infrastructure' => ['servers', 'domains'],
            'contacts' => ['contacts'],
            'enterprises' => ['enterprises'],
            'projects' => ['projects'],
            'services' => ['services'],
            'tasks' => ['tasks'],
        ];

        if (! isset($tableMap[$moduleKey]))
        {
            return false;
        }

        foreach ($tableMap[$moduleKey] as $table)
        {
            if (DB::getSchemaBuilder()->hasTable($table))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Get number of teams using this module
     */
    protected function getTeamsCount(Module $module)
    {
        return DB::table('module_team')
            ->where('module_id', $module->id)
            ->where('status', 1)
            ->count();
    }
}
