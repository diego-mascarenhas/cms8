<?php

namespace App\Services\WhatsApp;

use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskStatus;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Detects a pasted task sheet (CSV) from WhatsApp and creates Task rows for the team.
 * Expected header (commas or semicolons): Concepto, Propuesta, Cliente, Importe, IVA, IRPF, Fecha envío, Estado, Nota.
 * Optional leading line or prefix "task.store" is ignored so users can label the paste like a command.
 */
class WhatsAppTaskSheetImportService
{
    private const MAX_ROWS = 100;

    /**
     * If the message is a task sheet import, process it and return the WhatsApp reply; otherwise null.
     */
    public function tryHandle(string $body, ?User $user, int $teamId): ?string
    {
        if ($user === null || $teamId < 1)
        {
            return null;
        }

        $parsed = $this->parseSheet($body);
        if ($parsed === null)
        {
            return null;
        }

        if (! $user->teams()->where('teams.id', $teamId)->exists())
        {
            return 'Tu usuario no pertenece a este equipo. No se importaron tareas.';
        }

        if (! $this->userCanImportTasksForTeam($user, $teamId))
        {
            return 'No tenés permiso para crear tareas en Humano. Pedile a un administrador del equipo que te asigne el permiso correspondiente.';
        }

        $responsibleId = $this->resolveResponsibleId($user, $teamId);
        if ($responsibleId === null)
        {
            return 'No se pudo determinar el responsable de las tareas. Contactá soporte.';
        }

        $boardId = $this->resolveBoardId($teamId);

        /** @var array<int, array<string, string>> $rows */
        $rows = $parsed['rows'];
        if (count($rows) > self::MAX_ROWS)
        {
            return 'El archivo tiene demasiadas filas (máximo '.self::MAX_ROWS.'). Dividilo en partes más chicas.';
        }

        $createdTitles = [];
        $errors = [];

        Task::query()->getModel()->getConnection()->transaction(function () use ($rows, $teamId, $boardId, $responsibleId, &$createdTitles, &$errors): void
        {
            foreach ($rows as $index => $row)
            {
                $lineNum = $index + 2;
                try
                {
                    $concepto = trim($row['concepto'] ?? '');
                    if ($concepto === '')
                    {
                        continue;
                    }

                    $title = Str::limit($concepto, 255, '');
                    $description = $this->buildDescription($row);
                    $statusId = $this->resolveStatusId($row['estado'] ?? '');
                    [$startDate, $dueDate] = $this->resolveStartAndDueDates($row['fecha_envio'] ?? '');

                    Task::withoutGlobalScopes()->create([
                        'team_id' => $teamId,
                        'board_id' => $boardId,
                        'category_id' => null,
                        'responsible_id' => $responsibleId,
                        'title' => $title,
                        'description' => $description,
                        'estimated_hours' => null,
                        'start_date' => $startDate,
                        'due_date' => $dueDate,
                        'status_id' => $statusId,
                        'order' => 0,
                    ]);

                    $createdTitles[] = $title;
                } catch (\Throwable $e)
                {
                    $errors[] = "Fila {$lineNum}: ".$e->getMessage();
                }
            }
        });

        if ($createdTitles === [] && $errors === [])
        {
            return 'No encontré filas con Concepto relleno. Revisá el formato y volvé a enviarlo.';
        }

        $lines = ['✅ Se crearon '.count($createdTitles).' tarea(s) en Humano:'];
        foreach (array_slice($createdTitles, 0, 15) as $t)
        {
            $lines[] = '• '.Str::limit($t, 80, '…');
        }
        if (count($createdTitles) > 15)
        {
            $lines[] = '… y '.(count($createdTitles) - 15).' más.';
        }
        if ($errors !== [])
        {
            $lines[] = '';
            $lines[] = 'Advertencias:';
            foreach (array_slice($errors, 0, 5) as $err)
            {
                $lines[] = $err;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Team owners, Jetstream team admins/editors, and Spatie admin/root can import even without task.store on the user.
     */
    private function userCanImportTasksForTeam(User $user, int $teamId): bool
    {
        if ($user->can('task.store'))
        {
            return true;
        }

        $team = Team::withoutGlobalScopes()->find($teamId);
        if ($team !== null && (int) $team->user_id === (int) $user->id)
        {
            return true;
        }

        if ($user->hasRole(['admin', 'root']))
        {
            return true;
        }

        $membership = $user->teams()->where('teams.id', $teamId)->first();
        $pivotRole = strtolower((string) ($membership?->pivot?->role ?? ''));

        return in_array($pivotRole, ['admin', 'editor'], true);
    }

    /**
     * @return array{rows: array<int, array<string, string>>}|null
     */
    private function parseSheet(string $body): ?array
    {
        $normalized = str_replace("\r\n", "\n", trim($body));
        $normalized = str_replace("\r", "\n", $normalized);
        $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $normalized) ?? $normalized;
        $normalized = $this->stripLeadingTaskStoreCommand($normalized);

        return (new WhatsAppConceptoSheetParser)->parse($normalized);
    }

    /**
     * Remove a leading "task.store" label (same line or line before the header).
     */
    private function stripLeadingTaskStoreCommand(string $body): string
    {
        $body = preg_replace('/^\s*task\.store(?:\s+|\s*\R)/iu', '', $body) ?? $body;
        $body = preg_replace('/^\s*task\.store\s*$/ium', '', $body) ?? $body;

        return trim($body);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function buildDescription(array $row): string
    {
        $parts = [];
        $labels = [
            'propuesta' => 'Propuesta',
            'cliente' => 'Cliente',
            'importe' => 'Importe',
            'iva' => 'IVA',
            'irpf' => 'IRPF',
            'fecha_envio' => 'Fecha envío',
            'estado' => 'Estado (import)',
            'nota' => 'Nota',
        ];

        foreach ($labels as $key => $label)
        {
            $val = trim($row[$key] ?? '');
            if ($val !== '')
            {
                $parts[] = $label.': '.$val;
            }
        }

        $block = implode("\n", $parts);

        if ($block === '')
        {
            return '';
        }

        return "Importado desde WhatsApp\n\n".$block;
    }

    private function normalizeLabel(string $s): string
    {
        $s = mb_strtolower(trim($s));
        static $map = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        ];
        $s = strtr($s, $map);
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;

        return $s;
    }

    private function resolveStatusId(string $estadoRaw): int
    {
        $e = $this->normalizeLabel($estadoRaw);
        $name = match (true)
        {
            $e === '' => 'TO_DO',
            str_contains($e, 'pendiente') => 'TO_DO',
            str_contains($e, 'por hacer') => 'TO_DO',
            str_contains($e, 'hacer') && str_contains($e, 'por') => 'TO_DO',
            str_contains($e, 'enviad') => 'DONE',
            str_contains($e, 'complet') => 'DONE',
            str_contains($e, 'hecho') => 'DONE',
            str_contains($e, 'cerrad') => 'DONE',
            str_contains($e, 'progreso') => 'IN_PROGRESS',
            str_contains($e, 'curso') => 'IN_PROGRESS',
            str_contains($e, 'revis') => 'REVIEW',
            default => 'TO_DO',
        };

        $id = TaskStatus::where('name', $name)->value('id');

        return $id !== null ? (int) $id : 1;
    }

    /**
     * @return array{0: string, 1: string} Y-m-d start, Y-m-d due
     */
    private function resolveStartAndDueDates(string $fechaRaw): array
    {
        $today = now()->toDateString();
        $parsed = $this->parseSpanishDate($fechaRaw);
        if ($parsed === null)
        {
            return [$today, Carbon::parse($today)->addDays(7)->toDateString()];
        }

        return [$parsed, $parsed];
    }

    private function parseSpanishDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '')
        {
            return null;
        }

        foreach (['j/n/Y', 'd/m/Y', 'j-n-Y', 'd-m-Y', 'j/n/y', 'd/m/y'] as $fmt)
        {
            try
            {
                $c = Carbon::createFromFormat($fmt, $raw);
                if ($c instanceof Carbon)
                {
                    return $c->toDateString();
                }
            } catch (\Throwable)
            {
                continue;
            }
        }

        return null;
    }

    private function resolveResponsibleId(User $user, int $teamId): ?int
    {
        if (! $user->teams()->where('teams.id', $teamId)->exists())
        {
            return null;
        }

        return (int) $user->id;
    }

    private function resolveBoardId(int $teamId): int
    {
        $board = TaskBoard::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('is_default', true)
            ->first();

        if ($board !== null)
        {
            return (int) $board->id;
        }

        $any = TaskBoard::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->orderBy('order')
            ->orderBy('id')
            ->first();

        if ($any !== null)
        {
            return (int) $any->id;
        }

        $created = TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'name' => 'Default',
            'description' => 'Default',
            'is_default' => true,
            'order' => 0,
        ]);

        return (int) $created->id;
    }
}
