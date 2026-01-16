<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\Project;
use App\Models\Service;
use App\Models\Task;
use Illuminate\Console\Command;

class ManageContactDataCommand extends Command
{
    protected $signature = 'contact:manage-data
							{--contact-id= : Specific contact ID to manage}
							{--list : List all contacts with data}
							{--view : View contact data}
							{--validate : Validate contact data structure}
							{--import : Import data to sections}
							{--section= : Specific section to import to (enterprise|project|task|service|all)}
							{--dry-run : Show what would be imported without making changes}
							{--list-sections : List top-level sections of the data field}
							{--validate-software : Validate and display software data from contact}
							{--import-software : Import software data and remove from contact data}
							{--validate-fares : Validate and display fares data from contact}
							{--import-fares : Import fares data and remove from contact data}
							{--report : Generate comprehensive import report}
							{--all : Report for all contacts in a team}
							{--team-id= : Team ID for all-contacts report}
							{--show-contacts : Show contact names in the report}';

    protected $description = 'Manage contact data from JSON fields - view, validate, and import to different sections';

    protected $availableSections = [
        'enterprise' => 'Enterprise data',
        'project' => 'Project data',
        'task' => 'Task data',
        'service' => 'Service data',
        'all' => 'All sections',
    ];

    public function handle()
    {
        $this->line('=== Contact Data Management Tool ===');

        if ($this->option('list'))
        {
            $this->listContacts();

            return;
        }
        if ($this->option('view'))
        {
            $this->viewContactData();

            return;
        }
        if ($this->option('validate'))
        {
            $this->validateContactData();

            return;
        }
        if ($this->option('import'))
        {
            $this->importDataToSections();

            return;
        }
        if ($this->option('list-sections'))
        {
            $contactId = $this->option('contact-id');
            if (! $contactId)
            {
                $contactId = $this->ask('Enter contact ID to list data sections');
            }
            $contact = \App\Models\Contact::find($contactId);
            if (! $contact)
            {
                $this->error("Contact with ID {$contactId} not found.");

                return;
            }
            $sections = $this->getDataSections($contact);
            $this->info("Sections in 'data' field for contact {$contact->name} (ID: {$contact->id}):");
            if (empty($sections))
            {
                $this->line('  (No sections found)');
            } else
            {
                foreach ($sections as $section)
                {
                    $this->line("  - $section");
                }
            }

            return;
        }
        if ($this->option('validate-software'))
        {
            $this->validateSoftwareData();

            return;
        }
        if ($this->option('import-software'))
        {
            $this->importSoftwareData();

            return;
        }
        if ($this->option('validate-fares'))
        {
            $this->validateFaresData();

            return;
        }
        if ($this->option('import-fares'))
        {
            $this->importFaresData();

            return;
        }
        if ($this->option('report'))
        {
            if ($this->option('all'))
            {
                $teamId = $this->option('team-id');
                if (! $teamId)
                {
                    $teamId = $this->ask('Enter team ID to report for');
                }
                $showContacts = $this->option('show-contacts');
                $this->generateTeamImportReport($teamId, $showContacts);
            } else
            {
                $this->generateImportReport();
            }

            return;
        }
        $this->showInteractiveMenu();
    }

    protected function showInteractiveMenu()
    {
        $options = [
            'List contacts',
            'View contact data',
            'Validate contact',
            'Import data to sections',
            'List data sections',
            'Validate software',
            'Import software',
            'Validate fares',
            'Import fares',
            'Reporte de faltantes por equipo',
            'Exit',
        ];
        $choice = $this->choice('Select an action', $options);
        switch ($choice)
        {
            case 'List contacts':
                $this->listContacts();
                break;
            case 'View contact data':
                $this->viewContactData();
                break;
            case 'Validate contact':
                $this->validateContactData();
                break;
            case 'Import data to sections':
                $this->importDataToSections();
                break;
            case 'List data sections':
                $this->handle(['--list-sections' => true]);
                break;
            case 'Validate software':
                $this->validateSoftwareData();
                break;
            case 'Import software':
                $this->importSoftwareData();
                break;
            case 'Validate fares':
                $this->validateFaresData();
                break;
            case 'Import fares':
                $this->importFaresData();
                break;
            case 'Reporte de faltantes por equipo':
                $teamId = $this->ask('Enter team ID to report for');
                $showContacts = $this->confirm('Show contact names in the report?', false);
                $this->generateTeamImportReport($teamId, $showContacts);
                break;
            case 'Exit':
                $this->info('Goodbye!');
                break;
        }
    }

    protected function listContacts()
    {
        $this->info('📋 Listing contacts with data...');

        $contactId = $this->option('contact-id');

        $query = Contact::whereNotNull('data')
            ->where('data', '!=', '{}')
            ->where('data', '!=', 'null');

        if ($contactId)
        {
            $query->where('id', $contactId);
        }

        $contacts = $query->with(['team', 'responsible'])
            ->get();

        if ($contacts->isEmpty())
        {
            $this->warn('No contacts found with data.');

            return;
        }

        $rows = [];
        foreach ($contacts as $contact)
        {
            $dataKeys = is_array($contact->data) ? array_keys($contact->data) : [];
            $dataSummary = ! empty($dataKeys) ? implode(', ', array_slice($dataKeys, 0, 3)).(count($dataKeys) > 3 ? '...' : '') : 'No keys';

            $rows[] = [
                $contact->id,
                $contact->name,
                $contact->email ?? 'N/A',
                $contact->team->name ?? 'N/A',
                $contact->responsible->name ?? 'N/A',
                $dataSummary,
                $contact->created_at->format('Y-m-d H:i'),
            ];
        }

        $this->table(
            ['ID', 'Name', 'Email', 'Team', 'Responsible', 'Data Keys', 'Created'],
            $rows,
        );

        $this->info("Found {$contacts->count()} contacts with data.");
    }

    protected function viewContactData()
    {
        $contactId = $this->option('contact-id');

        if (! $contactId)
        {
            $contactId = $this->ask('Enter contact ID to view data');
        }

        $contact = Contact::find($contactId);

        if (! $contact)
        {
            $this->error("Contact with ID {$contactId} not found.");

            return;
        }

        if (! $contact->data)
        {
            $this->warn("Contact {$contact->name} has no data field.");

            return;
        }

        $this->info("📊 Contact Data for: {$contact->name} (ID: {$contact->id})");
        $this->line('');

        // Display basic contact info
        $this->info('Basic Information:');
        $this->table(['Field', 'Value'], [
            ['Name', $contact->name],
            ['Email', $contact->email ?? 'N/A'],
            ['Phone', $contact->phone ?? 'N/A'],
            ['Team', $contact->team->name ?? 'N/A'],
            ['Responsible', $contact->responsible->name ?? 'N/A'],
            ['Created', $contact->created_at->format('Y-m-d H:i:s')],
        ]);

        // Display data structure
        $this->info('Data Structure:');
        $this->displayJsonData($contact->data, 0);

        // Show data keys summary
        if (is_array($contact->data) || is_object($contact->data))
        {
            $this->info('Available Data Keys:');
            $dataArray = is_object($contact->data) ? (array) $contact->data : $contact->data;
            $keys = array_keys($dataArray);
            foreach ($keys as $index => $key)
            {
                $value = $dataArray[$key];
                $type = is_array($value) ? 'array('.count($value).')' : gettype($value);
                $preview = is_string($value) ? substr($value, 0, 50).(strlen($value) > 50 ? '...' : '') : json_encode($value);
                $this->line('  '.($index + 1).". {$key} ({$type}): {$preview}");
            }
        }
    }

    protected function displayJsonData($data, $level = 0)
    {
        $indent = str_repeat('  ', $level);

        if (is_array($data) || is_object($data))
        {
            $dataArray = is_object($data) ? (array) $data : $data;
            foreach ($dataArray as $key => $value)
            {
                if (is_array($value) || is_object($value))
                {
                    $this->line("{$indent}📁 {$key}:");
                    $this->displayJsonData($value, $level + 1);
                } else
                {
                    $displayValue = is_string($value) ? $value : json_encode($value);
                    $this->line("{$indent}📄 {$key}: {$displayValue}");
                }
            }
        } else
        {
            $displayValue = is_string($data) ? $data : json_encode($data);
            $this->line("{$indent}📄 {$displayValue}");
        }
    }

    protected function validateContactData()
    {
        $contactId = $this->option('contact-id');

        if (! $contactId)
        {
            $contactId = $this->ask('Enter contact ID to validate data');
        }

        $contact = Contact::with(['user.roles', 'user.teams', 'user.currentTeam.settings'])->find($contactId);

        if (! $contact)
        {
            $this->error("Contact with ID {$contactId} not found.");

            return;
        }

        $this->info("🔍 Validating contact: {$contact->name} (ID: {$contact->id})");
        $this->line('');

        $validationResults = $this->validateContactBasicData($contact);

        $this->info('Validation Results:');
        foreach ($validationResults as $section => $result)
        {
            $status = $result['valid'] ? '✅' : '❌';
            $this->line("{$status} {$section}: {$result['message']}");

            if (! $result['valid'] && ! empty($result['errors']))
            {
                foreach ($result['errors'] as $error)
                {
                    $this->line("	- {$error}");
                }
            }
        }

        // Show import readiness
        $readySections = array_filter($validationResults, fn ($r) => $r['valid']);
        if (! empty($readySections))
        {
            $this->info('');
            $this->info('📋 Ready for import:');
            foreach (array_keys($readySections) as $section)
            {
                $this->line("  - {$section}");
            }
        }
    }

    protected function validateSoftwareData()
    {
        $contactId = $this->option('contact-id');

        if (! $contactId)
        {
            $contactId = $this->ask('Enter contact ID to validate software data');
        }

        $contact = Contact::find($contactId);

        if (! $contact)
        {
            $this->error("Contact with ID {$contactId} not found.");

            return;
        }

        // Check for both 'software' and 'softwares' sections
        $softwareData = null;
        $sectionName = '';

        if (isset($contact->data->software))
        {
            $softwareData = $contact->data->software;
            $sectionName = 'software';
        } elseif (isset($contact->data->softwares))
        {
            $softwareData = $contact->data->softwares;
            $sectionName = 'softwares';
        }

        if (! $softwareData)
        {
            $this->warn("Contact {$contact->name} has no software data to validate.");

            return;
        }

        $this->info("🔍 Validating software data for contact: {$contact->name} (ID: {$contact->id})");
        $this->info("📁 Section: {$sectionName}");
        $this->line('');

        $softwareArray = is_object($softwareData) ? (array) $softwareData : $softwareData;

        if (empty($softwareArray))
        {
            $this->warn('No software entries found in data.');

            return;
        }

        $this->info('📋 Software entries found:');
        $this->line('');

        foreach ($softwareArray as $index => $software)
        {
            $softwareObj = is_object($software) ? (array) $software : $software;

            // Handle both string format and object format
            if (is_string($software))
            {
                $softwareName = $this->normalizeSpaces($software);
                $softwareObj = ['name' => $softwareName];
            } else
            {
                $softwareName = $this->normalizeSpaces($softwareObj['name'] ?? $softwareObj['software_name'] ?? 'Unknown');
            }

            // Check if software exists in team
            $existingSoftware = \App\Models\Software::where('team_id', $contact->team_id)
                ->where('name', $softwareName)
                ->first();

            // Check if software is already linked to this contact
            $isLinked = $contact->softwares()->where('name', $softwareName)->exists();

            $this->line("Software #{$index}: {$softwareName}");

            // Show status indicators
            $teamStatus = $existingSoftware ? '✅' : '❌';
            $linkStatus = $isLinked ? '✅' : '❌';

            $this->line("  Team exists: {$teamStatus} ".($existingSoftware ? "ID: {$existingSoftware->id}" : 'Not found'));
            $this->line("  Linked to contact: {$linkStatus}");

            // Show all software data
            foreach ($softwareObj as $key => $value)
            {
                $displayValue = is_array($value) ? json_encode($value) : $value;
                $this->line("  {$key}: {$displayValue}");
            }
            $this->line('');
        }

        $this->info('Total software entries: '.count($softwareArray));
        $this->line('');
        $this->info('Legend:');
        $this->line('  ✅ = Exists/Linked');
        $this->line('  ❌ = Not found/Not linked');
    }

    protected function validateFaresData()
    {
        $contactId = $this->option('contact-id');

        if (! $contactId)
        {
            $contactId = $this->ask('Enter contact ID to validate fares data');
        }

        $contact = Contact::find($contactId);

        if (! $contact)
        {
            $this->error("Contact with ID {$contactId} not found.");

            return;
        }

        // Check for both 'fare' and 'fares' sections
        $faresData = null;
        $sectionName = '';

        if (isset($contact->data->fare))
        {
            $faresData = $contact->data->fare;
            $sectionName = 'fare';
        } elseif (isset($contact->data->fares))
        {
            $faresData = $contact->data->fares;
            $sectionName = 'fares';
        }

        if (! $faresData)
        {
            $this->warn("Contact {$contact->name} has no fares data to validate.");

            return;
        }

        $this->info("🔍 Validating fares data for contact: {$contact->name} (ID: {$contact->id})");
        $this->info("📁 Section: {$sectionName}");
        $this->line('');

        $faresArray = is_object($faresData) ? (array) $faresData : $faresData;

        if (empty($faresArray))
        {
            $this->warn('No fares entries found in data.');

            return;
        }

        $this->info('📋 Fares entries found:');
        $this->line('');

        foreach ($faresArray as $index => $fare)
        {
            $fareObj = is_object($fare) ? (array) $fare : $fare;

            // Handle both string format and object format
            if (is_string($fare))
            {
                $fareName = $this->normalizeSpaces($fare);
                $fareObj = ['name' => $fareName];
            } else
            {
                $fareName = $this->normalizeSpaces($fareObj['name'] ?? $fareObj['fare_name'] ?? $fareObj['title'] ?? 'Unknown');
            }

            // Check if fare exists in team
            $existingFare = \App\Models\Fare::where('team_id', $contact->team_id)
                ->where('name', $fareName)
                ->first();

            // Check if fare is already linked to this contact
            $isLinked = $contact->fares()->where('name', $fareName)->exists();

            $this->line("Fare #{$index}: {$fareName}");

            // Show status indicators
            $teamStatus = $existingFare ? '✅' : '❌';
            $linkStatus = $isLinked ? '✅' : '❌';

            $this->line("  Team exists: {$teamStatus} ".($existingFare ? "ID: {$existingFare->id}" : 'Not found'));
            $this->line("  Linked to contact: {$linkStatus}");

            // Show all fare data
            foreach ($fareObj as $key => $value)
            {
                $displayValue = is_array($value) ? json_encode($value) : $value;
                $this->line("  {$key}: {$displayValue}");
            }
            $this->line('');
        }

        $this->info('Total fares entries: '.count($faresArray));
        $this->line('');
        $this->info('Legend:');
        $this->line('  ✅ = Exists/Linked');
        $this->line('  ❌ = Not found/Not linked');
    }

    protected function validateDataStructure($data)
    {
        $data = is_object($data) ? (array) $data : $data;
        $validationResults = [];
        $errors = [];
        $foundFields = [];

        // Validate enterprise data
        if (isset($data['enterprise']))
        {
            $enterpriseResult = $this->validateEnterpriseData($data['enterprise']);
            $validationResults['Enterprise'] = $enterpriseResult;
            if ($enterpriseResult['valid'])
            {
                $foundFields[] = 'enterprise';
            }
        } else
        {
            $validationResults['Enterprise'] = [
                'valid' => false,
                'message' => 'No enterprise data found',
                'errors' => ['Enterprise section not found in data'],
            ];
        }

        // Validate project data
        if (isset($data['project']))
        {
            $projectResult = $this->validateProjectData($data['project']);
            $validationResults['Project'] = $projectResult;
            if ($projectResult['valid'])
            {
                $foundFields[] = 'project';
            }
        } else
        {
            $validationResults['Project'] = [
                'valid' => false,
                'message' => 'No project data found',
                'errors' => ['Project section not found in data'],
            ];
        }

        // Validate task data
        if (isset($data['task']))
        {
            $taskResult = $this->validateTaskData($data['task']);
            $validationResults['Task'] = $taskResult;
            if ($taskResult['valid'])
            {
                $foundFields[] = 'task';
            }
        } else
        {
            $validationResults['Task'] = [
                'valid' => false,
                'message' => 'No task data found',
                'errors' => ['Task section not found in data'],
            ];
        }

        // Validate service data
        if (isset($data['service']))
        {
            $serviceResult = $this->validateServiceData($data['service']);
            $validationResults['Service'] = $serviceResult;
            if ($serviceResult['valid'])
            {
                $foundFields[] = 'service';
            }
        } else
        {
            $validationResults['Service'] = [
                'valid' => false,
                'message' => 'No service data found',
                'errors' => ['Service section not found in data'],
            ];
        }

        if (empty($foundFields))
        {
            $errors[] = 'No valid data sections found';
        }

        return [
            'valid' => empty($errors),
            'message' => empty($errors) ? "Ready ({count($foundFields)} fields)" : 'Invalid data',
            'errors' => $errors,
            'fields' => $foundFields,
        ];
    }

    protected function validateEnterpriseData($data)
    {
        $enterpriseFields = ['company_name', 'business_name', 'cuit', 'address', 'city', 'postal_code'];
        $foundFields = [];
        $errors = [];

        // Convert object to array if needed
        $dataArray = is_object($data) ? (array) $data : $data;

        foreach ($enterpriseFields as $field)
        {
            if (isset($dataArray[$field]) && ! empty($dataArray[$field]))
            {
                $foundFields[] = $field;
            }
        }

        if (count($foundFields) < 2)
        {
            $errors[] = 'Need at least 2 fields from: '.implode(', ', $enterpriseFields);
        }

        // Validate specific fields if present
        if (isset($dataArray['cuit']) && ! preg_match('/^\d{2}-\d{8}-\d{1}$/', $dataArray['cuit']))
        {
            $errors[] = 'CUIT format should be XX-XXXXXXXX-X';
        }

        if (isset($dataArray['email']) && ! filter_var($dataArray['email'], FILTER_VALIDATE_EMAIL))
        {
            $errors[] = 'Invalid email format';
        }

        return [
            'valid' => empty($errors),
            'message' => empty($errors) ? "Ready ({count($foundFields)} fields)" : 'Invalid data',
            'errors' => $errors,
            'fields' => $foundFields,
        ];
    }

    protected function validateProjectData($data)
    {
        $projectFields = ['project_name', 'project_description', 'project_type', 'start_date', 'end_date'];
        $foundFields = [];
        $errors = [];

        // Convert object to array if needed
        $dataArray = is_object($data) ? (array) $data : $data;

        foreach ($projectFields as $field)
        {
            if (isset($dataArray[$field]) && ! empty($dataArray[$field]))
            {
                $foundFields[] = $field;
            }
        }

        if (count($foundFields) < 2)
        {
            $errors[] = 'Need at least 2 fields from: '.implode(', ', $projectFields);
        }

        // Validate dates if present
        if (isset($dataArray['start_date']) && ! strtotime($dataArray['start_date']))
        {
            $errors[] = 'Invalid start_date format';
        }

        if (isset($dataArray['end_date']) && ! strtotime($dataArray['end_date']))
        {
            $errors[] = 'Invalid end_date format';
        }

        return [
            'valid' => empty($errors),
            'message' => empty($errors) ? "Ready ({count($foundFields)} fields)" : 'Invalid data',
            'errors' => $errors,
            'fields' => $foundFields,
        ];
    }

    protected function validateTaskData($data)
    {
        $taskFields = ['task_title', 'task_description', 'priority', 'due_date'];
        $foundFields = [];
        $errors = [];

        // Convert object to array if needed
        $dataArray = is_object($data) ? (array) $data : $data;

        foreach ($taskFields as $field)
        {
            if (isset($dataArray[$field]) && ! empty($dataArray[$field]))
            {
                $foundFields[] = $field;
            }
        }

        if (count($foundFields) < 2)
        {
            $errors[] = 'Need at least 2 fields from: '.implode(', ', $taskFields);
        }

        // Validate priority if present
        if (isset($dataArray['priority']) && ! in_array(strtolower($dataArray['priority']), ['low', 'medium', 'high', 'urgent']))
        {
            $errors[] = 'Priority should be: low, medium, high, or urgent';
        }

        // Validate due date if present
        if (isset($dataArray['due_date']) && ! strtotime($dataArray['due_date']))
        {
            $errors[] = 'Invalid due_date format';
        }

        return [
            'valid' => empty($errors),
            'message' => empty($errors) ? "Ready ({count($foundFields)} fields)" : 'Invalid data',
            'errors' => $errors,
            'fields' => $foundFields,
        ];
    }

    protected function validateServiceData($data)
    {
        $serviceFields = ['service_name', 'service_type', 'domain', 'hosting_provider', 'price'];
        $foundFields = [];
        $errors = [];

        // Convert object to array if needed
        $dataArray = is_object($data) ? (array) $data : $data;

        foreach ($serviceFields as $field)
        {
            if (isset($dataArray[$field]) && ! empty($dataArray[$field]))
            {
                $foundFields[] = $field;
            }
        }

        if (count($foundFields) < 2)
        {
            $errors[] = 'Need at least 2 fields from: '.implode(', ', $serviceFields);
        }

        // Validate price if present
        if (isset($dataArray['price']) && ! is_numeric($dataArray['price']))
        {
            $errors[] = 'Price should be a number';
        }

        // Validate domain if present
        if (isset($dataArray['domain']) && ! filter_var($dataArray['domain'], FILTER_VALIDATE_DOMAIN))
        {
            $errors[] = 'Invalid domain format';
        }

        return [
            'valid' => empty($errors),
            'message' => empty($errors) ? "Ready ({count($foundFields)} fields)" : 'Invalid data',
            'errors' => $errors,
            'fields' => $foundFields,
        ];
    }

    protected function validateContactBasicData($contact)
    {
        $validationResults = [];
        $errors = [];

        // Check if contact has associated user
        if (! $contact->user)
        {
            $validationResults['User Association'] = [
                'valid' => false,
                'message' => 'No user associated',
                'errors' => ['Contact has no associated user account'],
            ];
        } else
        {
            $validationResults['User Association'] = [
                'valid' => true,
                'message' => 'User associated',
                'errors' => [],
            ];
        }

        // Check basic contact fields (now only name and email)
        $basicFields = ['name', 'email'];
        $missingFields = [];

        foreach ($basicFields as $field)
        {
            if (empty($contact->$field))
            {
                $missingFields[] = $field;
            }
        }

        if (empty($missingFields))
        {
            $validationResults['Basic Information'] = [
                'valid' => true,
                'message' => 'All required fields present',
                'errors' => [],
            ];
        } else
        {
            $validationResults['Basic Information'] = [
                'valid' => false,
                'message' => 'Missing required fields',
                'errors' => ['Missing fields: '.implode(', ', $missingFields)],
            ];
        }

        // Check if contact has data field
        if (! $contact->data)
        {
            $validationResults['JSON Data'] = [
                'valid' => false,
                'message' => 'No JSON data available',
                'errors' => ['Contact has no data field content'],
            ];
        } else
        {
            $dataValidation = $this->validateDataStructure($contact->data);
            $validationResults['JSON Data'] = $dataValidation;
        }

        return $validationResults;
    }

    protected function importDataToSections()
    {
        $contactId = $this->option('contact-id');
        $section = $this->option('section');
        $dryRun = $this->option('dry-run');

        if (! $contactId)
        {
            $contactId = $this->ask('Enter contact ID to import data from');
        }

        $contact = Contact::find($contactId);

        if (! $contact)
        {
            $this->error("Contact with ID {$contactId} not found.");

            return;
        }

        if (! $contact->data)
        {
            $this->warn("Contact {$contact->name} has no data to import.");

            return;
        }

        if ($dryRun)
        {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->info("📤 Importing data from contact: {$contact->name} (ID: {$contact->id})");
        $this->line('');

        // Validate data first
        $validationResults = $this->validateDataStructure($contact->data);
        $validSections = array_filter($validationResults, fn ($r) => $r['valid']);

        if (empty($validSections))
        {
            $this->error('No valid data found for import. Please validate the data first.');

            return;
        }

        // Determine which sections to import
        $sectionsToImport = [];
        if ($section && $section !== 'all')
        {
            if (isset($validSections[ucfirst($section)]))
            {
                $sectionsToImport[$section] = $validSections[ucfirst($section)];
            } else
            {
                $this->error("Section '{$section}' is not valid or has no valid data.");

                return;
            }
        } else
        {
            $sectionsToImport = $validSections;
        }

        // Show what will be imported
        $this->info('Sections to import:');
        foreach (array_keys($sectionsToImport) as $sectionName)
        {
            $this->line("  - {$sectionName}");
        }

        if (! $dryRun && ! $this->confirm('Do you want to proceed with the import?'))
        {
            $this->info('Import cancelled.');

            return;
        }

        $importResults = [];

        // Import each section
        foreach ($sectionsToImport as $sectionName => $validation)
        {
            $this->info("Importing {$sectionName}...");

            try
            {
                $result = match (strtolower($sectionName))
                {
                    'enterprise' => $this->importEnterpriseData($contact, $dryRun),
                    'project' => $this->importProjectData($contact, $dryRun),
                    'task' => $this->importTaskData($contact, $dryRun),
                    'service' => $this->importServiceData($contact, $dryRun),
                    default => ['success' => false, 'message' => "Unknown section: {$sectionName}"]
                };

                $importResults[$sectionName] = $result;

                $status = $result['success'] ? '✅' : '❌';
                $this->line("{$status} {$sectionName}: {$result['message']}");
            } catch (\Exception $e)
            {
                $importResults[$sectionName] = ['success' => false, 'message' => $e->getMessage()];
                $this->line("❌ {$sectionName}: {$e->getMessage()}");
            }
        }

        // Summary
        $this->info('');
        $this->info('Import Summary:');
        $successCount = count(array_filter($importResults, fn ($r) => $r['success']));
        $totalCount = count($importResults);

        $this->line("Successfully imported: {$successCount}/{$totalCount}");

        if ($dryRun)
        {
            $this->warn('This was a dry run. No actual data was imported.');
        }
    }

    protected function importEnterpriseData($contact, $dryRun = false)
    {
        $data = $contact->data;

        // Convert object to array if needed
        $dataArray = is_object($data) ? (array) $data : $data;

        // Check if enterprise already exists for this contact
        $existingEnterprise = Enterprise::where('responsible_id', $contact->id)->first();

        if ($existingEnterprise && ! $this->confirm('Enterprise already exists for this contact. Update it?'))
        {
            return ['success' => false, 'message' => 'Import cancelled by user'];
        }

        $enterpriseData = [
            'name' => $dataArray['company_name'] ?? $dataArray['business_name'] ?? $contact->name,
            'code' => $dataArray['cuit'] ?? null,
            'website' => $dataArray['website'] ?? null,
            'phone' => $dataArray['phone'] ?? $contact->phone,
            'email' => $dataArray['email'] ?? $contact->email,
            'whatsapp' => $dataArray['whatsapp'] ?? null,
            'status_id' => 1, // Active
            'responsible_id' => $contact->id,
            'team_id' => $contact->team_id,
            'type_id' => 1, // Client
        ];

        if ($dryRun)
        {
            $this->line("  Would create/update enterprise: {$enterpriseData['name']}");

            return ['success' => true, 'message' => 'Would be created/updated'];
        }

        try
        {
            if ($existingEnterprise)
            {
                $existingEnterprise->update($enterpriseData);

                return ['success' => true, 'message' => "Updated enterprise: {$enterpriseData['name']}"];
            } else
            {
                Enterprise::create($enterpriseData);

                return ['success' => true, 'message' => "Created enterprise: {$enterpriseData['name']}"];
            }
        } catch (\Exception $e)
        {
            return ['success' => false, 'message' => "Error: {$e->getMessage()}"];
        }
    }

    protected function importProjectData($contact, $dryRun = false)
    {
        $data = $contact->data;

        // Convert object to array if needed
        $dataArray = is_object($data) ? (array) $data : $data;

        $projectData = [
            'name' => $dataArray['project_name'] ?? 'Project from '.$contact->name,
            'description' => $dataArray['project_description'] ?? null,
            'start_date' => $dataArray['start_date'] ?? null,
            'due_date' => $dataArray['end_date'] ?? null,
            'status_id' => 1, // Active
            'responsible_id' => $contact->responsible_id ?? $contact->creator_id,
            'team_id' => $contact->team_id,
            'creator_id' => $contact->creator_id,
        ];

        if ($dryRun)
        {
            $this->line("  Would create project: {$projectData['name']}");

            return ['success' => true, 'message' => 'Would be created'];
        }

        try
        {
            $project = Project::create($projectData);

            // Link project to contact
            $project->contacts()->attach($contact->id, [
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['success' => true, 'message' => "Created project: {$projectData['name']}"];
        } catch (\Exception $e)
        {
            return ['success' => false, 'message' => "Error: {$e->getMessage()}"];
        }
    }

    protected function importTaskData($contact, $dryRun = false)
    {
        $data = $contact->data;

        // Convert object to array if needed
        $dataArray = is_object($data) ? (array) $data : $data;

        $taskData = [
            'title' => $dataArray['task_title'] ?? 'Task from '.$contact->name,
            'description' => $dataArray['task_description'] ?? null,
            'start_date' => $dataArray['start_date'] ?? null,
            'due_date' => $dataArray['due_date'] ?? null,
            'status_id' => 1, // Active
            'responsible_id' => $contact->responsible_id ?? $contact->creator_id,
            'team_id' => $contact->team_id,
            'creator_id' => $contact->creator_id,
        ];

        if ($dryRun)
        {
            $this->line("  Would create task: {$taskData['title']}");

            return ['success' => true, 'message' => 'Would be created'];
        }

        try
        {
            $task = Task::create($taskData);

            return ['success' => true, 'message' => "Created task: {$taskData['title']}"];
        } catch (\Exception $e)
        {
            return ['success' => false, 'message' => "Error: {$e->getMessage()}"];
        }
    }

    protected function importServiceData($contact, $dryRun = false)
    {
        $data = $contact->data;

        // Convert object to array if needed
        $dataArray = is_object($data) ? (array) $data : $data;

        // Get default category for services
        $defaultCategory = Category::where('module_id', function ($query)
        {
            $query->select('id')->from('modules')->where('key', 'services');
        })->first();

        $serviceData = [
            'name' => $dataArray['service_name'] ?? 'Service from '.$contact->name,
            'description' => $dataArray['service_description'] ?? null,
            'category_id' => $defaultCategory->id ?? 1,
            'enterprise_id' => null, // Will be set if enterprise exists
            'operation' => 'sell',
            'price' => $dataArray['price'] ?? 0,
            'currency_id' => 1, // Default currency
            'status' => 1, // Active
            'responsible_id' => $contact->responsible_id ?? $contact->creator_id,
            'team_id' => $contact->team_id,
            'data' => [
                'domain' => $dataArray['domain'] ?? null,
                'hosting_provider' => $dataArray['hosting_provider'] ?? null,
                'service_type' => $dataArray['service_type'] ?? null,
            ],
        ];

        // Try to find associated enterprise
        $enterprise = Enterprise::where('responsible_id', $contact->id)->first();
        if ($enterprise)
        {
            $serviceData['enterprise_id'] = $enterprise->id;
        }

        if ($dryRun)
        {
            $this->line("  Would create service: {$serviceData['name']}");

            return ['success' => true, 'message' => 'Would be created'];
        }

        try
        {
            $service = Service::create($serviceData);

            return ['success' => true, 'message' => "Created service: {$serviceData['name']}"];
        } catch (\Exception $e)
        {
            return ['success' => false, 'message' => "Error: {$e->getMessage()}"];
        }
    }

    protected function isAdmin()
    {
        // Temporarily allow all users for testing
        return true;
        // return auth()->check() && auth()->user()->hasRole('admin');
    }

    protected function generateImportReport($contactId = null, $suppressHeader = false)
    {
        // If a Contact model is passed, use it directly
        if ($contactId instanceof \App\Models\Contact)
        {
            $contact = $contactId;
        } else
        {
            // Only prompt for contact ID if not in team/global mode
            if (! $contactId)
            {
                $contactId = $this->option('contact-id');
                if (! $contactId)
                {
                    $contactId = $this->ask('Enter contact ID to generate import report');
                }
            }
            $contact = Contact::find($contactId);
        }

        if (! $contact)
        {
            $this->error("Contact with ID {$contactId} not found.");

            return;
        }

        if (! $contact->data)
        {
            if (! $suppressHeader)
            {
                $this->warn("Contact {$contact->name} has no data to analyze.");
            }

            return;
        }

        if (! $suppressHeader)
        {
            $this->info("📊 MISSING ITEMS REPORT for contact: {$contact->name} (ID: {$contact->id})");
            $this->info("Team: {$contact->team->name} (ID: {$contact->team_id})");
            $this->line('');
        }

        $data = (array) $contact->data;
        $totalMissing = 0;
        $hasMissingItems = false;

        // Software Analysis
        if (isset($data['software']) || isset($data['softwares']))
        {
            $softwareData = $data['software'] ?? $data['softwares'];
            $softwareArray = is_object($softwareData) ? (array) $softwareData : $softwareData;

            if (! empty($softwareArray))
            {
                $missingSoftware = $this->getMissingSoftware($contact, $softwareArray);
                if (! empty($missingSoftware))
                {
                    $this->info('🔧 MISSING SOFTWARE:');
                    foreach ($missingSoftware as $name)
                    {
                        $this->line("  - {$name}");
                    }
                    $totalMissing += count($missingSoftware);
                    $hasMissingItems = true;
                }
            }
        }

        // Fares/Rates Analysis
        if (isset($data['fare']) || isset($data['fares']) || isset($data['rates']))
        {
            $faresData = $data['fare'] ?? $data['fares'] ?? $data['rates'];
            $faresArray = is_object($faresData) ? (array) $faresData : $faresData;

            if (! empty($faresArray))
            {
                $missingFares = $this->getMissingFares($contact, $faresArray);
                if (! empty($missingFares))
                {
                    $this->info('💰 MISSING FARES/RATES:');
                    foreach ($missingFares as $name)
                    {
                        $this->line("  - {$name}");
                    }
                    $totalMissing += count($missingFares);
                    $hasMissingItems = true;
                }
            }
        }

        // Languages Analysis
        if (isset($data['language']) || isset($data['languages']))
        {
            $languagesData = $data['language'] ?? $data['languages'];
            $languagesArray = is_object($languagesData) ? (array) $languagesData : $languagesData;

            if (! empty($languagesArray))
            {
                $missingLanguages = $this->getMissingLanguages($contact, $languagesArray);
                if (! empty($missingLanguages))
                {
                    $this->info('🌍 MISSING LANGUAGES:');
                    foreach ($missingLanguages as $name)
                    {
                        $this->line("  - {$name}");
                    }
                    $totalMissing += count($missingLanguages);
                    $hasMissingItems = true;
                }
            }
        }

        // Language Variants Analysis
        if (isset($data['language_variants']) || isset($data['language_variants']))
        {
            $variantsData = $data['language_variants'];
            $variantsArray = is_object($variantsData) ? (array) $variantsData : $variantsData;

            if (! empty($variantsArray))
            {
                $missingVariants = $this->getMissingLanguageVariants($contact, $variantsArray);
                if (! empty($missingVariants))
                {
                    $this->info('🔄 MISSING LANGUAGE VARIANTS:');
                    foreach ($missingVariants as $variant)
                    {
                        $this->line("  - {$variant}");
                    }
                    $totalMissing += count($missingVariants);
                    $hasMissingItems = true;
                }
            }
        }

        // Services Analysis
        if (isset($data['service']) || isset($data['services']))
        {
            $servicesData = $data['service'] ?? $data['services'];
            $servicesArray = is_object($servicesData) ? (array) $servicesData : $servicesData;

            if (! empty($servicesArray))
            {
                $missingServices = $this->getMissingServices($contact, $servicesArray);
                if (! empty($missingServices))
                {
                    $this->info('🔧 MISSING SERVICES:');
                    foreach ($missingServices as $name)
                    {
                        $this->line("  - {$name}");
                    }
                    $totalMissing += count($missingServices);
                    $hasMissingItems = true;
                }
            }
        }

        // Country Analysis
        if (isset($data['country']) || isset($data['countries']))
        {
            $countriesData = $data['country'] ?? $data['countries'];
            $countriesArray = is_object($countriesData) ? (array) $countriesData : $countriesData;

            if (! empty($countriesArray))
            {
                $missingCountries = $this->getMissingCountries($contact, $countriesArray);
                if (! empty($missingCountries))
                {
                    $this->info('🌍 MISSING COUNTRIES:');
                    foreach ($missingCountries as $name)
                    {
                        $this->line("  - {$name}");
                    }
                    $totalMissing += count($missingCountries);
                    $hasMissingItems = true;
                }
            }
        }

        // Other sections analysis
        $otherSections = $this->getOtherSections($contact, $data);
        if (! empty($otherSections))
        {
            $this->info('📋 OTHER SECTIONS (not analyzed):');
            foreach ($otherSections as $section => $items)
            {
                $this->line("  - {$section}: ".count($items).' items');
            }
        }

        // Summary
        $this->line('');
        if ($hasMissingItems)
        {
            $this->warn("⚠️  Total missing items: {$totalMissing}");
            $this->info('💡 Use import commands to create these items.');
        } else
        {
            $this->info('✅ All items already exist in the database.');
        }
    }

    protected function generateTeamImportReport($teamId, $showContacts = false)
    {
        $contacts = \App\Models\Contact::where('team_id', $teamId)->get();
        $team = \App\Models\Team::find($teamId);

        if (! $team)
        {
            $this->error("Team with ID {$teamId} not found.");

            return;
        }

        $this->info('📊 GLOBAL MISSING ITEMS REPORT');
        $this->info("Team: {$team->name} (ID: {$teamId})");
        $this->info("Total Contacts: {$contacts->count()}");
        $this->line('');

        // Initialize global missing items arrays
        $globalMissing = [
            'software' => [],
            'fares' => [],
            'languages' => [],
            'language_variants' => [],
            'services' => [],
            'countries' => [],
            'other_sections' => [],
        ];

        $contactsWithMissing = [];

        // Analyze each contact
        foreach ($contacts as $contact)
        {
            if (! $contact->data)
            {
                continue;
            }

            $data = (array) $contact->data;
            $contactHasMissing = false;

            // Software Analysis
            if (isset($data['software']) || isset($data['softwares']))
            {
                $softwareData = $data['software'] ?? $data['softwares'];
                $softwareArray = is_object($softwareData) ? (array) $softwareData : $softwareData;

                if (! empty($softwareArray))
                {
                    $missingSoftware = $this->getMissingSoftware($contact, $softwareArray);
                    foreach ($missingSoftware as $name)
                    {
                        if (! isset($globalMissing['software'][$name]))
                        {
                            $globalMissing['software'][$name] = [];
                        }
                        $globalMissing['software'][$name][] = $contact->name.' (ID: '.$contact->id.')';
                        $contactHasMissing = true;
                    }
                }
            }

            // Fares/Rates Analysis
            if (isset($data['fare']) || isset($data['fares']) || isset($data['rates']))
            {
                $faresData = $data['fare'] ?? $data['fares'] ?? $data['rates'];
                $faresArray = is_object($faresData) ? (array) $faresData : $faresData;

                if (! empty($faresArray))
                {
                    $missingFares = $this->getMissingFares($contact, $faresArray);
                    foreach ($missingFares as $name)
                    {
                        if (! isset($globalMissing['fares'][$name]))
                        {
                            $globalMissing['fares'][$name] = [];
                        }
                        $globalMissing['fares'][$name][] = $contact->name.' (ID: '.$contact->id.')';
                        $contactHasMissing = true;
                    }
                }
            }

            // Languages Analysis
            if (isset($data['language']) || isset($data['languages']))
            {
                $languagesData = $data['language'] ?? $data['languages'];
                $languagesArray = is_object($languagesData) ? (array) $languagesData : $languagesData;

                if (! empty($languagesArray))
                {
                    $missingLanguages = $this->getMissingLanguages($contact, $languagesArray);
                    foreach ($missingLanguages as $name)
                    {
                        if (! isset($globalMissing['languages'][$name]))
                        {
                            $globalMissing['languages'][$name] = [];
                        }
                        $globalMissing['languages'][$name][] = $contact->name.' (ID: '.$contact->id.')';
                        $contactHasMissing = true;
                    }
                }
            }

            // Language Variants Analysis
            if (isset($data['language_variants']))
            {
                $variantsData = $data['language_variants'];
                $variantsArray = is_object($variantsData) ? (array) $variantsData : $variantsData;

                if (! empty($variantsArray))
                {
                    $missingVariants = $this->getMissingLanguageVariants($contact, $variantsArray);
                    foreach ($missingVariants as $variant)
                    {
                        if (! isset($globalMissing['language_variants'][$variant]))
                        {
                            $globalMissing['language_variants'][$variant] = [];
                        }
                        $globalMissing['language_variants'][$variant][] = $contact->name.' (ID: '.$contact->id.')';
                        $contactHasMissing = true;
                    }
                }
            }

            // Services Analysis
            if (isset($data['service']) || isset($data['services']))
            {
                $servicesData = $data['service'] ?? $data['services'];
                $servicesArray = is_object($servicesData) ? (array) $servicesData : $servicesData;

                if (! empty($servicesArray))
                {
                    $missingServices = $this->getMissingServices($contact, $servicesArray);
                    foreach ($missingServices as $name)
                    {
                        if (! isset($globalMissing['services'][$name]))
                        {
                            $globalMissing['services'][$name] = [];
                        }
                        $globalMissing['services'][$name][] = $contact->name.' (ID: '.$contact->id.')';
                        $contactHasMissing = true;
                    }
                }
            }

            // Country Analysis
            if (isset($data['country']) || isset($data['countries']))
            {
                $countriesData = $data['country'] ?? $data['countries'];
                $countriesArray = is_object($countriesData) ? (array) $countriesData : $countriesData;

                if (! empty($countriesArray))
                {
                    $missingCountries = $this->getMissingCountries($contact, $countriesArray);
                    foreach ($missingCountries as $name)
                    {
                        if (! isset($globalMissing['countries'][$name]))
                        {
                            $globalMissing['countries'][$name] = [];
                        }
                        $globalMissing['countries'][$name][] = $contact->name.' (ID: '.$contact->id.')';
                        $contactHasMissing = true;
                    }
                }
            }

            if ($contactHasMissing)
            {
                $contactsWithMissing[] = $contact->name.' (ID: '.$contact->id.')';
            }
        }

        // Display global grouped report
        $totalMissingItems = 0;
        $hasAnyMissing = false;

        // Software Section
        if (! empty($globalMissing['software']))
        {
            $this->info('🔧 MISSING SOFTWARE:');
            foreach ($globalMissing['software'] as $softwareName => $contactsList)
            {
                $this->line("  - {$softwareName} (missing in ".count($contactsList).' contacts)');
                if ($showContacts)
                {
                    foreach ($contactsList as $contactInfo)
                    {
                        $this->line("	• {$contactInfo}");
                    }
                }
                $totalMissingItems += count($contactsList);
            }
            $this->line('');
            $hasAnyMissing = true;
        }

        // Fares/Rates Section
        if (! empty($globalMissing['fares']))
        {
            $this->info('💰 MISSING FARES/RATES:');
            foreach ($globalMissing['fares'] as $fareName => $contactsList)
            {
                $this->line("  - {$fareName} (missing in ".count($contactsList).' contacts)');
                if ($showContacts)
                {
                    foreach ($contactsList as $contactInfo)
                    {
                        $this->line("	• {$contactInfo}");
                    }
                }
                $totalMissingItems += count($contactsList);
            }
            $this->line('');
            $hasAnyMissing = true;
        }

        // Languages Section
        if (! empty($globalMissing['languages']))
        {
            $this->info('🌍 MISSING LANGUAGES:');
            foreach ($globalMissing['languages'] as $languageName => $contactsList)
            {
                $this->line("  - {$languageName} (missing in ".count($contactsList).' contacts)');
                if ($showContacts)
                {
                    foreach ($contactsList as $contactInfo)
                    {
                        $this->line("	• {$contactInfo}");
                    }
                }
                $totalMissingItems += count($contactsList);
            }
            $this->line('');
            $hasAnyMissing = true;
        }

        // Language Variants Section
        if (! empty($globalMissing['language_variants']))
        {
            $this->info('🔄 MISSING LANGUAGE VARIANTS:');
            foreach ($globalMissing['language_variants'] as $variantName => $contactsList)
            {
                $this->line("  - {$variantName} (missing in ".count($contactsList).' contacts)');
                if ($showContacts)
                {
                    foreach ($contactsList as $contactInfo)
                    {
                        $this->line("	• {$contactInfo}");
                    }
                }
                $totalMissingItems += count($contactsList);
            }
            $this->line('');
            $hasAnyMissing = true;
        }

        // Services Section
        if (! empty($globalMissing['services']))
        {
            $this->info('🔧 MISSING SERVICES:');
            foreach ($globalMissing['services'] as $serviceName => $contactsList)
            {
                $this->line("  - {$serviceName} (missing in ".count($contactsList).' contacts)');
                if ($showContacts)
                {
                    foreach ($contactsList as $contactInfo)
                    {
                        $this->line("	• {$contactInfo}");
                    }
                }
                $totalMissingItems += count($contactsList);
            }
            $this->line('');
            $hasAnyMissing = true;
        }

        // Countries Section
        if (! empty($globalMissing['countries']))
        {
            $this->info('🌍 MISSING COUNTRIES:');
            foreach ($globalMissing['countries'] as $countryName => $contactsList)
            {
                $this->line("  - {$countryName} (missing in ".count($contactsList).' contacts)');
                if ($showContacts)
                {
                    foreach ($contactsList as $contactInfo)
                    {
                        $this->line("	• {$contactInfo}");
                    }
                }
                $totalMissingItems += count($contactsList);
            }
            $this->line('');
            $hasAnyMissing = true;
        }

        // Summary
        $this->line(str_repeat('=', 60));
        if ($hasAnyMissing)
        {
            $this->warn("⚠️  Total missing items: {$totalMissingItems}");
            $this->info('📋 Contacts with missing items: '.count($contactsWithMissing));
            $this->info('💡 Use import commands to create these items.');
        } else
        {
            $this->info('✅ All items already exist in the database for all contacts.');
        }
    }

    protected function analyzeSoftwareData($contact, $softwareArray)
    {
        $stats = ['total' => 0, 'to_create' => 0, 'to_link' => 0, 'already_linked' => 0];
        $toCreate = [];
        $toLink = [];

        foreach ($softwareArray as $software)
        {
            $softwareObj = is_object($software) ? (array) $software : $software;

            if (is_string($software))
            {
                $softwareName = $this->normalizeSpaces($software);
            } else
            {
                $softwareName = $this->normalizeSpaces($softwareObj['name'] ?? $softwareObj['software_name'] ?? 'Unknown');
            }

            $stats['total']++;

            $existingSoftware = \App\Models\Software::where('team_id', $contact->team_id)
                ->where('name', $softwareName)
                ->first();

            $isLinked = $contact->softwares()->where('name', $softwareName)->exists();

            if ($isLinked)
            {
                $stats['already_linked']++;
            } elseif ($existingSoftware)
            {
                $stats['to_link']++;
                $toLink[] = $softwareName;
            } else
            {
                $stats['to_create']++;
                $toCreate[] = $softwareName;
            }
        }

        if (! empty($toCreate))
        {
            $this->line('  ➕ To CREATE:');
            foreach ($toCreate as $name)
            {
                $this->line("	- {$name}");
            }
        }

        if (! empty($toLink))
        {
            $this->line('  🔗 To LINK:');
            foreach ($toLink as $name)
            {
                $this->line("	- {$name}");
            }
        }

        return $stats;
    }

    protected function analyzeFaresData($contact, $faresArray)
    {
        $stats = ['total' => 0, 'to_create' => 0, 'to_link' => 0, 'already_linked' => 0];
        $toCreate = [];
        $toLink = [];

        foreach ($faresArray as $fare)
        {
            $fareObj = is_object($fare) ? (array) $fare : $fare;

            if (is_string($fare))
            {
                $fareName = $this->normalizeSpaces($fare);
            } else
            {
                $fareName = $this->normalizeSpaces($fareObj['name'] ?? $fareObj['fare_name'] ?? $fareObj['title'] ?? 'Unknown');
            }

            $stats['total']++;

            $existingFare = \App\Models\Fare::where('team_id', $contact->team_id)
                ->where('name', $fareName)
                ->first();

            $isLinked = $contact->fares()->where('name', $fareName)->exists();

            if ($isLinked)
            {
                $stats['already_linked']++;
            } elseif ($existingFare)
            {
                $stats['to_link']++;
                $toLink[] = $fareName;
            } else
            {
                $stats['to_create']++;
                $toCreate[] = $fareName;
            }
        }

        if (! empty($toCreate))
        {
            $this->line('  ➕ To CREATE:');
            foreach ($toCreate as $name)
            {
                $this->line("	- {$name}");
            }
        }

        if (! empty($toLink))
        {
            $this->line('  🔗 To LINK:');
            foreach ($toLink as $name)
            {
                $this->line("	- {$name}");
            }
        }

        return $stats;
    }

    protected function displaySectionStats($sectionName, $stats)
    {
        $this->line("  Total items: {$stats['total']}");
        $this->line("  To create: {$stats['to_create']}");
        $this->line("  To link: {$stats['to_link']}");
        $this->line("  Already linked: {$stats['already_linked']}");
        $this->line('');
    }

    protected function confirmImport($sectionName, $toCreate, $toLink)
    {
        if ($toCreate > 0)
        {
            $this->warn("⚠️  {$toCreate} new {$sectionName} items will be created.");
        }

        if ($toLink > 0)
        {
            $this->info("🔗 {$toLink} existing {$sectionName} items will be linked.");
        }

        if ($toCreate > 0 || $toLink > 0)
        {
            return $this->confirm("Do you want to proceed with the {$sectionName} import?");
        }

        return false;
    }

    protected function importSoftwareData()
    {
        $contactId = $this->option('contact-id');
        $dryRun = $this->option('dry-run');

        if (! $contactId)
        {
            $contactId = $this->ask('Enter contact ID to import software data');
        }

        $contact = Contact::find($contactId);

        if (! $contact)
        {
            $this->error("Contact with ID {$contactId} not found.");

            return;
        }

        // Check for both 'software' and 'softwares' sections
        $softwareData = null;
        $sectionName = '';

        if (isset($contact->data->software))
        {
            $softwareData = $contact->data->software;
            $sectionName = 'software';
        } elseif (isset($contact->data->softwares))
        {
            $softwareData = $contact->data->softwares;
            $sectionName = 'softwares';
        }

        if (! $softwareData)
        {
            $this->warn("Contact {$contact->name} has no software data to import.");

            return;
        }

        $softwareArray = is_object($softwareData) ? (array) $softwareData : $softwareData;

        if (empty($softwareArray))
        {
            $this->warn('No software entries found in data.');

            return;
        }

        // Analyze and confirm before import
        $stats = $this->analyzeSoftwareData($contact, $softwareArray);
        if (! $this->confirmImport('software', $stats['to_create'], $stats['to_link']))
        {
            $this->info('Import cancelled.');

            return;
        }

        if ($dryRun)
        {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->info("📤 Importing software data for contact: {$contact->name} (ID: {$contact->id})");
        $this->info("📁 Section: {$sectionName}");
        $this->line('');

        $importedCount = 0;
        $linkedCount = 0;
        $createdCount = 0;

        foreach ($softwareArray as $index => $software)
        {
            $softwareObj = is_object($software) ? (array) $software : $software;

            // Handle both string format and object format
            if (is_string($software))
            {
                $softwareName = $this->normalizeSpaces($software);
                $softwareObj = ['name' => $softwareName];
            } else
            {
                $softwareName = $this->normalizeSpaces($softwareObj['name'] ?? $softwareObj['software_name'] ?? 'Unknown');
            }

            $this->line("Processing: {$softwareName}");

            // Check if software exists in team
            $existingSoftware = \App\Models\Software::where('team_id', $contact->team_id)
                ->where('name', $softwareName)
                ->first();

            // Check if software is already linked to this contact
            $isLinked = $contact->softwares()->where('name', $softwareName)->exists();

            if ($isLinked)
            {
                $this->line('  ⏭️  Already linked to contact');
                $linkedCount++;

                continue;
            }

            if ($existingSoftware)
            {
                // Link existing software to contact
                if (! $dryRun)
                {
                    $contact->softwares()->attach($existingSoftware->id);
                }
                $this->line("  🔗 Linked existing software (ID: {$existingSoftware->id})");
                $linkedCount++;
            } else
            {
                // Create new software and link it
                if (! $dryRun)
                {
                    $newSoftware = \App\Models\Software::create([
                        'team_id' => $contact->team_id,
                        'name' => $softwareName,
                        'description' => $softwareObj['description'] ?? $softwareObj['version'] ?? null,
                        'version' => $softwareObj['version'] ?? null,
                        'license_type' => $softwareObj['license_type'] ?? null,
                        'status_id' => 1, // Assuming 1 is active status
                    ]);

                    $contact->softwares()->attach($newSoftware->id);
                }
                $this->line('  ➕ Created and linked new software');
                $createdCount++;
            }

            $importedCount++;
        }

        // Remove software section from data field
        if (! $dryRun && $importedCount > 0)
        {
            $data = (array) $contact->data;
            unset($data[$sectionName]);
            $contact->data = $data;
            $contact->save();

            $this->line('');
            $this->info("🗑️  Removed {$sectionName} section from contact data");
        }

        $this->line('');
        $this->info('📊 Import Summary:');
        $this->line('  Total processed: '.count($softwareArray));
        $this->line("  Already linked: {$linkedCount}");
        $this->line("  Newly created: {$createdCount}");
        $this->line("  Total imported: {$importedCount}");

        if ($dryRun)
        {
            $this->warn('This was a dry run. No changes were made.');
        } else
        {
            $this->info('✅ Software import completed successfully!');
        }
    }

    protected function importFaresData()
    {
        $contactId = $this->option('contact-id');
        $dryRun = $this->option('dry-run');

        if (! $contactId)
        {
            $contactId = $this->ask('Enter contact ID to import fares data');
        }

        $contact = Contact::find($contactId);

        if (! $contact)
        {
            $this->error("Contact with ID {$contactId} not found.");

            return;
        }

        // Check for both 'fare' and 'fares' sections
        $faresData = null;
        $sectionName = '';

        if (isset($contact->data->fare))
        {
            $faresData = $contact->data->fare;
            $sectionName = 'fare';
        } elseif (isset($contact->data->fares))
        {
            $faresData = $contact->data->fares;
            $sectionName = 'fares';
        }

        if (! $faresData)
        {
            $this->warn("Contact {$contact->name} has no fares data to import.");

            return;
        }

        $faresArray = is_object($faresData) ? (array) $faresData : $faresData;

        if (empty($faresArray))
        {
            $this->warn('No fares entries found in data.');

            return;
        }

        // Analyze and confirm before import
        $stats = $this->analyzeFaresData($contact, $faresArray);
        if (! $this->confirmImport('fares', $stats['to_create'], $stats['to_link']))
        {
            $this->info('Import cancelled.');

            return;
        }

        if ($dryRun)
        {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->info("📤 Importing fares data for contact: {$contact->name} (ID: {$contact->id})");
        $this->info("📁 Section: {$sectionName}");
        $this->line('');

        $importedCount = 0;
        $linkedCount = 0;
        $createdCount = 0;

        foreach ($faresArray as $index => $fare)
        {
            $fareObj = is_object($fare) ? (array) $fare : $fare;

            // Handle both string format and object format
            if (is_string($fare))
            {
                $fareName = $this->normalizeSpaces($fare);
                $fareObj = ['name' => $fareName];
            } else
            {
                $fareName = $this->normalizeSpaces($fareObj['name'] ?? $fareObj['fare_name'] ?? $fareObj['title'] ?? 'Unknown');
            }

            $this->line("Processing: {$fareName}");

            // Check if fare exists in team
            $existingFare = \App\Models\Fare::where('team_id', $contact->team_id)
                ->where('name', $fareName)
                ->first();

            // Check if fare is already linked to this contact
            $isLinked = $contact->fares()->where('name', $fareName)->exists();

            if ($isLinked)
            {
                $this->line('  ⏭️  Already linked to contact');
                $linkedCount++;

                continue;
            }

            if ($existingFare)
            {
                // Link existing fare to contact
                if (! $dryRun)
                {
                    $contact->fares()->attach($existingFare->id);
                }
                $this->line("  🔗 Linked existing fare (ID: {$existingFare->id})");
                $linkedCount++;
            } else
            {
                // Create new fare and link it
                if (! $dryRun)
                {
                    $newFare = \App\Models\Fare::create([
                        'team_id' => $contact->team_id,
                        'name' => $fareName,
                        'glosary_id' => $fareObj['glosary_id'] ?? null,
                        'type_id' => $fareObj['type_id'] ?? 1, // Assuming 1 is default type
                    ]);

                    $contact->fares()->attach($newFare->id);
                }
                $this->line('  ➕ Created and linked new fare');
                $createdCount++;
            }

            $importedCount++;
        }

        // Remove fares section from data field
        if (! $dryRun && $importedCount > 0)
        {
            $data = (array) $contact->data;
            unset($data[$sectionName]);
            $contact->data = $data;
            $contact->save();

            $this->line('');
            $this->info("🗑️  Removed {$sectionName} section from contact data");
        }

        $this->line('');
        $this->info('📊 Import Summary:');
        $this->line('  Total processed: '.count($faresArray));
        $this->line("  Already linked: {$linkedCount}");
        $this->line("  Newly created: {$createdCount}");
        $this->line("  Total imported: {$importedCount}");

        if ($dryRun)
        {
            $this->warn('This was a dry run. No changes were made.');
        } else
        {
            $this->info('✅ Fares import completed successfully!');
        }
    }

    protected function getDataSections($contact)
    {
        if (! $contact->data)
        {
            return [];
        }
        $data = is_object($contact->data) ? (array) $contact->data : $contact->data;

        return array_keys($data);
    }

    protected function normalizeSpaces($text)
    {
        // Remove multiple spaces and trim
        return preg_replace('/\s+/', ' ', trim($text));
    }

    protected function getMissingSoftware($contact, $softwareArray)
    {
        $missing = [];

        foreach ($softwareArray as $software)
        {
            $softwareObj = is_object($software) ? (array) $software : $software;

            if (is_string($software))
            {
                $softwareName = $this->normalizeSpaces($software);
            } else
            {
                $softwareName = $this->normalizeSpaces($softwareObj['name'] ?? $softwareObj['software_name'] ?? 'Unknown');
            }

            $exists = \App\Models\Software::where('team_id', $contact->team_id)
                ->where('name', $softwareName)
                ->exists();

            if (! $exists)
            {
                $missing[] = $softwareName;
            }
        }

        return $missing;
    }

    protected function getMissingFares($contact, $faresArray)
    {
        $missing = [];

        foreach ($faresArray as $fare)
        {
            $fareObj = is_object($fare) ? (array) $fare : $fare;

            if (is_string($fare))
            {
                $fareName = $this->normalizeSpaces($fare);
            } else
            {
                $fareName = $this->normalizeSpaces($fareObj['name'] ?? $fareObj['fare_name'] ?? $fareObj['title'] ?? 'Unknown');
            }

            $exists = \App\Models\Fare::where('team_id', $contact->team_id)
                ->where('name', $fareName)
                ->exists();

            if (! $exists)
            {
                $missing[] = $fareName;
            }
        }

        return $missing;
    }

    protected function getMissingLanguages($contact, $languagesArray)
    {
        $missing = [];

        foreach ($languagesArray as $language)
        {
            $languageObj = is_object($language) ? (array) $language : $language;

            if (is_string($language))
            {
                $languageName = $this->normalizeSpaces($language);
                $languageCode = strtolower(substr($languageName, 0, 2));
            } else
            {
                $languageName = $this->normalizeSpaces($languageObj['name'] ?? $languageObj['language_name'] ?? 'Unknown');
                $languageCode = $languageObj['code'] ?? strtolower(substr($languageName, 0, 2));
            }

            $exists = \App\Models\Language::where('code', $languageCode)
                ->orWhere('name', $languageName)
                ->exists();

            if (! $exists)
            {
                $missing[] = $languageName;
            }
        }

        return $missing;
    }

    protected function getMissingLanguageVariants($contact, $variantsArray)
    {
        $missing = [];

        foreach ($variantsArray as $variant)
        {
            $variantObj = is_object($variant) ? (array) $variant : $variant;

            if (is_string($variant))
            {
                $variantName = $this->normalizeSpaces($variant);
            } else
            {
                $sourceLang = $this->normalizeSpaces($variantObj['source_language'] ?? $variantObj['source'] ?? 'Unknown');
                $targetLang = $this->normalizeSpaces($variantObj['target_language'] ?? $variantObj['target'] ?? 'Unknown');
                $variantName = "{$sourceLang} → {$targetLang}";
            }

            $missing[] = $variantName; // For now, just list all variants as they need manual review
        }

        return $missing;
    }

    protected function getMissingServices($contact, $servicesArray)
    {
        $missing = [];

        foreach ($servicesArray as $service)
        {
            $serviceObj = is_object($service) ? (array) $service : $service;

            if (is_string($service))
            {
                $serviceName = $this->normalizeSpaces($service);
            } else
            {
                $serviceName = $this->normalizeSpaces($serviceObj['name'] ?? $serviceObj['service_name'] ?? 'Unknown');
            }

            // Services don't have team_id, so we check by name globally
            $exists = \App\Models\Service::where('description', 'like', "%{$serviceName}%")
                ->orWhere('operation', 'like', "%{$serviceName}%")
                ->exists();

            if (! $exists)
            {
                $missing[] = $serviceName;
            }
        }

        return $missing;
    }

    protected function getMissingCountries($contact, $countriesArray)
    {
        $missing = [];

        foreach ($countriesArray as $country)
        {
            $countryObj = is_object($country) ? (array) $country : $country;

            if (is_string($country))
            {
                $countryName = $this->normalizeSpaces($country);
            } else
            {
                $countryName = $this->normalizeSpaces($countryObj['name'] ?? $countryObj['country_name'] ?? 'Unknown');
            }

            $exists = \App\Models\Country::where('name', $countryName)
                ->orWhere('code', $countryName) // Assuming code might be the name or a specific code
                ->exists();

            if (! $exists)
            {
                $missing[] = $countryName;
            }
        }

        return $missing;
    }

    protected function getOtherSections($contact, $data)
    {
        $knownSections = ['software', 'softwares', 'fare', 'fares', 'rates', 'language', 'languages', 'language_variants', 'service', 'services', 'country'];
        $otherSections = [];

        foreach ($data as $section => $items)
        {
            if (! in_array($section, $knownSections))
            {
                $itemsArray = is_object($items) ? (array) $items : $items;
                if (is_array($itemsArray))
                {
                    $otherSections[$section] = $itemsArray;
                }
            }
        }

        return $otherSections;
    }
}
