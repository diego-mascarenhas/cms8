<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\Team;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppTaskSheetImportService;
use Database\Seeders\TaskStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WhatsAppTaskSheetImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function attachUserToTeam(User $user, Team $team, string $role = 'editor'): void
    {
        $user->teams()->attach($team->id, ['role' => $role]);
    }

    private function sampleSheet(): string
    {
        return <<<'CSV'
Concepto,Propuesta,Cliente,Importe,IVA,IRPF,Fecha envío,Estado,Nota
bbo Unicornio,Unicornio de BBO doblajes,BBO Subtitulado,€ 5000.00,,,24/3/2026,Enviada,
Web Símbica (kit digital performanze),Símbica web,Rocio Ovalle,€ 800.00,,,,Pendiente,
ANPA inscripciones,ANPA inscripciones,Pelayo García RPM,€ 800.00,,,,Pendiente,
CSV;
    }

    public function test_imports_rows_as_tasks(): void
    {
        $this->seed(TaskStatusSeeder::class);

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Default',
            'description' => 'Default',
            'is_default' => true,
            'order' => 0,
        ]);

        $user = User::factory()->create();
        $this->attachUserToTeam($user, $team);
        $perm = Permission::firstOrCreate(['name' => 'task.store', 'guard_name' => 'web']);
        $user->givePermissionTo($perm);

        $reply = app(WhatsAppTaskSheetImportService::class)->tryHandle($this->sampleSheet(), $user, (int) $team->id);

        $this->assertIsString($reply);
        $this->assertStringContainsString('Se crearon 3 tarea', $reply);

        $count = Task::withoutGlobalScopes()->where('team_id', $team->id)->count();
        $this->assertSame(3, $count);

        $unicorn = Task::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('title', 'bbo Unicornio')
            ->first();
        $this->assertNotNull($unicorn);
        $this->assertStringContainsString('Importado desde WhatsApp', (string) $unicorn->description);
        $this->assertStringContainsString('BBO Subtitulado', (string) $unicorn->description);
        $this->assertStringContainsString('€ 5000.00', (string) $unicorn->description);
        $this->assertSame((int) $user->id, (int) $unicorn->responsible_id);
    }

    public function test_imports_with_task_store_prefix_on_same_line(): void
    {
        $this->seed(TaskStatusSeeder::class);

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Default',
            'description' => 'Default',
            'is_default' => true,
            'order' => 0,
        ]);

        $user = User::factory()->create();
        $this->attachUserToTeam($user, $team);
        $perm = Permission::firstOrCreate(['name' => 'task.store', 'guard_name' => 'web']);
        $user->givePermissionTo($perm);

        $body = "task.store\n".$this->sampleSheet();
        $reply = app(WhatsAppTaskSheetImportService::class)->tryHandle($body, $user, (int) $team->id);

        $this->assertIsString($reply);
        $this->assertSame(3, Task::withoutGlobalScopes()->where('team_id', $team->id)->count());
    }

    public function test_imports_with_task_store_inline_before_header(): void
    {
        $this->seed(TaskStatusSeeder::class);

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Default',
            'description' => 'Default',
            'is_default' => true,
            'order' => 0,
        ]);

        $user = User::factory()->create();
        $this->attachUserToTeam($user, $team);
        $perm = Permission::firstOrCreate(['name' => 'task.store', 'guard_name' => 'web']);
        $user->givePermissionTo($perm);

        $body = 'task.store '.$this->sampleSheet();
        $reply = app(WhatsAppTaskSheetImportService::class)->tryHandle($body, $user, (int) $team->id);

        $this->assertIsString($reply);
        $this->assertSame(3, Task::withoutGlobalScopes()->where('team_id', $team->id)->count());
    }

    public function test_returns_null_for_non_sheet_message(): void
    {
        $this->seed(TaskStatusSeeder::class);
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $user = User::factory()->create();
        $this->attachUserToTeam($user, $team);

        $this->assertNull(app(WhatsAppTaskSheetImportService::class)->tryHandle('Hola, cómo estás?', $user, (int) $team->id));
    }

    public function test_returns_null_without_user(): void
    {
        $this->assertNull(app(WhatsAppTaskSheetImportService::class)->tryHandle($this->sampleSheet(), null, 1));
    }

    public function test_denies_without_permission(): void
    {
        $this->seed(TaskStatusSeeder::class);
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Default',
            'description' => 'Default',
            'is_default' => true,
            'order' => 0,
        ]);

        $user = User::factory()->create();
        $this->attachUserToTeam($user, $team, 'client');

        $reply = app(WhatsAppTaskSheetImportService::class)->tryHandle($this->sampleSheet(), $user, (int) $team->id);

        $this->assertIsString($reply);
        $this->assertStringContainsString('permiso', $reply);
        $this->assertSame(0, Task::withoutGlobalScopes()->where('team_id', $team->id)->count());
    }

    public function test_imports_when_jetstream_team_admin_without_task_store_permission(): void
    {
        $this->seed(TaskStatusSeeder::class);

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Default',
            'description' => 'Default',
            'is_default' => true,
            'order' => 0,
        ]);

        $user = User::factory()->create();
        $this->attachUserToTeam($user, $team, 'admin');

        $reply = app(WhatsAppTaskSheetImportService::class)->tryHandle($this->sampleSheet(), $user, (int) $team->id);

        $this->assertIsString($reply);
        $this->assertStringContainsString('Se crearon 3 tarea', $reply);
        $this->assertSame(3, Task::withoutGlobalScopes()->where('team_id', $team->id)->count());
    }

    public function test_imports_when_team_owner_without_task_store_permission(): void
    {
        $this->seed(TaskStatusSeeder::class);

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->teams()->syncWithoutDetaching([$team->id => ['role' => 'admin']]);
        TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Default',
            'description' => 'Default',
            'is_default' => true,
            'order' => 0,
        ]);

        $reply = app(WhatsAppTaskSheetImportService::class)->tryHandle($this->sampleSheet(), $owner, (int) $team->id);

        $this->assertIsString($reply);
        $this->assertStringContainsString('Se crearon 3 tarea', $reply);
        $this->assertSame(3, Task::withoutGlobalScopes()->where('team_id', $team->id)->count());
    }
}
