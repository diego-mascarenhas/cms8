<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Module;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskStatus;
use App\Models\Team;
use App\Models\User;
use App\Support\DemoTeam;
use Illuminate\Database\Seeder;

/**
 * Standalone tasks for /task/list?view=kanban (boards not linked to projects).
 *
 * Fresh install: applied automatically at the end of {@see TeamDemoSeeder} (migrate:fresh --seed).
 * Standalone: php artisan db:seed --class=DemoKanbanTasksSeeder
 */
class DemoKanbanTasksSeeder extends Seeder
{
    private const int TARGET_TASKS = 36;

    private const int TARGET_OWNER_TASKS = 5;

    /** @var list<string> */
    private const BOARD_NAMES = ['General', 'Comercial', 'Development'];

    public function run(): void
    {
        $team = Team::query()->where('name', 'Demo')->orderBy('id')->first();

        if ($team === null)
        {
            $this->command?->warn('DemoKanbanTasksSeeder: team "Demo" not found — skip.');

            return;
        }

        $this->command?->info('📋 Seeding demo kanban tasks (tablero general)...');

        $projectBoardIds = Project::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereNotNull('board_id')
            ->pluck('board_id');

        $boards = $this->ensureKanbanBoards($team, $projectBoardIds);

        $existing = Task::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereIn('board_id', $boards->pluck('id'))
            ->count();

        $toCreate = max(0, self::TARGET_TASKS - $existing);

        if ($toCreate === 0)
        {
            $this->command?->info("⏭️  Kanban tasks already present ({$existing}).");
        } else
        {
            $created = $this->createKanbanTasks($team, $boards, $toCreate, $existing);
            $this->command?->info(sprintf('✅ Demo kanban tasks: +%d (total %d on %d boards).', $created, $existing + $created, $boards->count()));
        }

        $ownerTasks = $this->ensureOwnerKanbanTasks($team, $boards);
        if ($ownerTasks > 0)
        {
            $this->command?->info("✅ Owner kanban tasks ensured: {$ownerTasks}");
        }

        DemoTeam::trimAdministrators($team);
    }

    private function createKanbanTasks(Team $team, $boards, int $toCreate, int $existing): int
    {
        $statusIds = TaskStatus::query()->orderBy('order')->pluck('id', 'name');
        $tasksModuleId = Module::query()->where('key', 'tasks')->value('id');
        $categories = $tasksModuleId
            ? Category::query()->where('team_id', $team->id)->where('module_id', $tasksModuleId)->pluck('id')
            : collect();

        $responsibleIds = $team->allUsers()
            ->reject(fn (User $user) => $user->hasRole('admin'))
            ->pluck('id')
            ->all();

        if ($responsibleIds === [])
        {
            $responsibleIds = [$team->user_id ?? User::query()->value('id')];
        }

        $titles = [
            'Seguimiento propuesta Nordic Retail',
            'Revisar facturas vencidas del mes',
            'Llamada de cierre — Cliente Premium',
            'Preparar demo plan Business',
            'Actualizar pipeline comercial',
            'Enviar presupuesto revisado',
            'Coordinar reunión onboarding',
            'Responder consultas bandeja demo',
            'Revisar contrato anual pendiente',
            'Confirmar videollamada comercial',
            'Actualizar datos contacto caliente',
            'Preparar informe semanal ventas',
            'Gestionar cobro domiciliación fallida',
            'Revisar propuesta upselling',
            'Agendar visita FoodTech Delivery',
            'Documentar acuerdo comercial',
            'Seguimiento WhatsApp sin leer',
            'Validar descuentos campaña mailer',
            'Preparar workshop Business',
            'Revisar KPIs dashboard equipo',
            'Contactar leads nuevos del mes',
            'Sincronizar CRM con campaña activa',
            'Revisar tareas vencidas hoy',
            'Preparar monthly sales review',
            'Actualizar categorías contactos demo',
            'Revisar integración API cliente',
            'Confirmar kick-off integración',
            'Enviar recordatorio reunión tarde',
            'Auditar oportunidades en funnel',
            'Preparar material comercial Q2',
            'Revisar feedback post-demo',
            'Planificar llamadas de prospección',
            'Actualizar plantillas email ventas',
            'Cerrar tareas administrativas pendientes',
            'Revisar pagos recibidos esta semana',
            'Organizar prioridades del día',
        ];

        $statusRotation = [
            $statusIds['TO_DO'] ?? 1,
            $statusIds['IN_PROGRESS'] ?? 2,
            $statusIds['REVIEW'] ?? 3,
            $statusIds['DONE'] ?? 4,
        ];

        $created = 0;
        $boardList = $boards->values();

        for ($i = 0; $i < $toCreate; $i++)
        {
            $n = $existing + $i + 1;
            $board = $boardList[$i % $boardList->count()];
            $title = $titles[$i % count($titles)];
            $uniqueTitle = $n <= count($titles) ? $title : $title.' #'.$n;

            Task::withoutGlobalScopes()->updateOrCreate(
                [
                    'team_id' => $team->id,
                    'board_id' => $board->id,
                    'title' => $uniqueTitle,
                ],
                [
                    'description' => 'Tarea demo para el tablero kanban comercial y operativo del equipo Demo.',
                    'category_id' => $categories->isNotEmpty() ? $categories->random() : null,
                    'responsible_id' => $responsibleIds[$i % count($responsibleIds)],
                    'status_id' => $statusRotation[$i % count($statusRotation)],
                    'order' => $i + 1,
                    'estimated_hours' => [2, 4, 6, 8][$i % 4],
                    'start_date' => now()->subDays($i % 14),
                    'due_date' => now()->addDays(3 + ($i % 21)),
                ],
            );

            $created++;
        }

        return $created;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\TaskBoard>  $boards
     */
    private function ensureOwnerKanbanTasks(Team $team, $boards): int
    {
        $owner = User::query()
            ->where('email', 'admin@humano.app')
            ->first()
            ?? User::query()->find($team->user_id);

        if ($owner === null || $boards->isEmpty())
        {
            return 0;
        }

        $statusIds = TaskStatus::query()->orderBy('order')->pluck('id', 'name');
        $board = $boards->firstWhere('name', 'General') ?? $boards->first();
        $categoryIds = $this->taskCategoryIdsByName($team);

        $definitions = [
            ['title' => 'Priorizar seguimientos de hoy', 'status' => 'TO_DO', 'category' => 'Cobranza'],
            ['title' => 'Llamada cierre — Cliente Premium', 'status' => 'IN_PROGRESS', 'category' => 'Presupuestos'],
            ['title' => 'Revisar propuesta Nordic Retail', 'status' => 'REVIEW', 'category' => 'Presupuestos'],
            ['title' => 'Enviar informe semanal comercial', 'status' => 'DONE', 'category' => 'Pagos'],
            ['title' => 'Confirmar demo plan Business', 'status' => 'TO_DO', 'category' => 'Presupuestos'],
        ];

        $created = 0;

        foreach ($definitions as $index => $definition)
        {
            Task::withoutGlobalScopes()->updateOrCreate(
                [
                    'team_id' => $team->id,
                    'board_id' => $board->id,
                    'title' => $definition['title'],
                ],
                [
                    'description' => 'Tarea demo asignada a Idóneo para el tablero kanban.',
                    'category_id' => $categoryIds[$definition['category']] ?? null,
                    'responsible_id' => $owner->id,
                    'status_id' => $statusIds[$definition['status']] ?? ($index + 1),
                    'order' => 100 + $index,
                    'estimated_hours' => 4,
                    'start_date' => now()->subDays($index),
                    'due_date' => now()->addDays(2 + $index),
                ],
            );

            $created++;
        }

        return $created;
    }

    /**
     * @return array<string, int>
     */
    private function taskCategoryIdsByName(Team $team): array
    {
        $tasksModuleId = Module::query()->where('key', 'tasks')->value('id');

        if ($tasksModuleId === null)
        {
            return [];
        }

        return Category::query()
            ->where('team_id', $team->id)
            ->where('module_id', $tasksModuleId)
            ->whereIn('name', ['Cobranza', 'Presupuestos', 'Pagos', 'Administración', 'Proyectos'])
            ->pluck('id', 'name')
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $projectBoardIds
     */
    private function ensureKanbanBoards(Team $team, $projectBoardIds): \Illuminate\Support\Collection
    {
        $boards = collect();

        foreach (self::BOARD_NAMES as $index => $name)
        {
            $board = TaskBoard::withoutGlobalScopes()->firstOrCreate(
                [
                    'team_id' => $team->id,
                    'name' => $name,
                ],
                [
                    'description' => 'Tablero demo: '.$name,
                    'is_default' => $name === 'General',
                    'order' => $index,
                ],
            );

            if (! $projectBoardIds->contains($board->id))
            {
                $boards->push($board);
            }
        }

        if ($boards->isNotEmpty())
        {
            return $boards;
        }

        return TaskBoard::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereNotIn('id', $projectBoardIds)
            ->orderBy('order')
            ->get();
    }
}
