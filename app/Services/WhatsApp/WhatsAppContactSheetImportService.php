<?php

namespace App\Services\WhatsApp;

use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Enterprise;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Detects a pasted contact sheet (CSV) with prefix "contact.store".
 * Expected headers (commas or semicolons), at least one of: Nombre, Email, Teléfono/Móvil.
 * Optional: Apellido, Empresa, Nota.
 */
class WhatsAppContactSheetImportService
{
    private const MAX_ROWS = 200;

    /**
     * If the message is a contact sheet import, process it and return the WhatsApp reply; otherwise null.
     */
    public function tryHandle(string $body, ?User $user, int $teamId): ?string
    {
        if ($user === null || $teamId < 1)
        {
            return null;
        }

        if (! $this->bodyHasContactStorePrefix($body))
        {
            return null;
        }

        $normalized = str_replace("\r\n", "\n", trim($body));
        $normalized = str_replace("\r", "\n", $normalized);
        $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $normalized) ?? $normalized;
        $normalized = $this->stripLeadingContactStoreCommand($normalized);

        $parsed = $this->parseContactSheet($normalized);
        if ($parsed === null)
        {
            return null;
        }

        if (! $user->teams()->where('teams.id', $teamId)->exists())
        {
            return 'Tu usuario no pertenece a este equipo. No se importaron contactos.';
        }

        if (! $this->userCanImportContactsForTeam($user, $teamId))
        {
            return 'No tenés permiso para crear contactos en Humano. Pedile a un administrador del equipo que te asigne el permiso correspondiente.';
        }

        /** @var array<int, array<string, string>> $rows */
        $rows = $parsed['rows'];
        if (count($rows) > self::MAX_ROWS)
        {
            return 'El archivo tiene demasiadas filas (máximo '.self::MAX_ROWS.'). Dividilo en partes más chicas.';
        }

        $statusId = $this->resolveDefaultContactStatusId();
        $createdNames = [];
        $errors = [];

        DB::transaction(function () use ($rows, $teamId, $user, $statusId, &$createdNames, &$errors): void
        {
            foreach ($rows as $index => $row)
            {
                $lineNum = $index + 2;
                try
                {
                    $nombre = trim($row['nombre'] ?? '');
                    $email = trim($row['email'] ?? '');
                    $telefono = trim($row['telefono'] ?? '');

                    if ($nombre === '' && $email === '' && $telefono === '')
                    {
                        continue;
                    }

                    if ($nombre === '')
                    {
                        if ($email !== '')
                        {
                            $nombre = Str::before($email, '@') ?: 'Importado';
                        } else
                        {
                            $nombre = 'Contacto importado';
                        }
                    }

                    $surname = trim($row['apellido'] ?? '') ?: null;
                    $nota = trim($row['nota'] ?? '');
                    $empresaLabel = trim($row['empresa'] ?? '');

                    $profileParts = [];
                    if ($nota !== '')
                    {
                        $profileParts[] = $nota;
                    }
                    if ($empresaLabel !== '')
                    {
                        $profileParts[] = 'Empresa (hoja): '.$empresaLabel;
                    }
                    $profileParts[] = 'Importado desde WhatsApp / asistente.';
                    $profile = implode("\n", $profileParts);

                    $enterpriseId = $this->resolveEnterpriseIdForLabel($teamId, $empresaLabel);

                    $contact = Contact::withoutGlobalScopes()->create([
                        'team_id' => $teamId,
                        'user_id' => null,
                        'current_enterprise_id' => $enterpriseId,
                        'name' => Str::limit($nombre, 255, ''),
                        'surname' => $surname !== null && $surname !== '' ? Str::limit($surname, 255, '') : null,
                        'email' => $email !== '' ? Str::limit($email, 255, '') : null,
                        'phone' => $telefono !== '' ? $telefono : null,
                        'creator_id' => $user->id,
                        'responsible_id' => $user->id,
                        'status_id' => $statusId,
                        'country' => 724,
                        'language' => 'es',
                        'profile' => $profile,
                    ]);

                    if ($enterpriseId !== null)
                    {
                        $contact->enterprises()->syncWithoutDetaching([$enterpriseId]);
                    }

                    $createdNames[] = $contact->name;
                } catch (\Throwable $e)
                {
                    $errors[] = "Fila {$lineNum}: ".$e->getMessage();
                }
            }
        });

        if ($createdNames === [] && $errors === [])
        {
            return 'No encontré filas con Nombre, Email o Teléfono. Revisá el encabezado y volvé a enviarlo.';
        }

        $lines = ['✅ Se crearon '.count($createdNames).' contacto(s) en Humano:'];
        foreach (array_slice($createdNames, 0, 15) as $n)
        {
            $lines[] = '• '.Str::limit($n, 80, '…');
        }
        if (count($createdNames) > 15)
        {
            $lines[] = '… y '.(count($createdNames) - 15).' más.';
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

    private function userCanImportContactsForTeam(User $user, int $teamId): bool
    {
        if ($user->can('contact.store'))
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

    private function bodyHasContactStorePrefix(string $body): bool
    {
        $normalized = str_replace("\r\n", "\n", trim($body));
        $normalized = str_replace("\r", "\n", $normalized);
        $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $normalized) ?? $normalized;

        return (bool) preg_match('/^\s*contact\.store(?:\s+|\s*$|\s*\R)/iu', $normalized);
    }

    private function stripLeadingContactStoreCommand(string $body): string
    {
        $body = preg_replace('/^\s*contact\.store(?:\s+|\s*\R)/iu', '', $body) ?? $body;
        $body = preg_replace('/^\s*contact\.store\s*$/ium', '', $body) ?? $body;

        return trim($body);
    }

    /**
     * @return array{rows: array<int, array<string, string>>}|null
     */
    private function parseContactSheet(string $body): ?array
    {
        $lines = array_values(array_filter(
            explode("\n", trim($body)),
            static fn (string $l): bool => trim($l) !== '',
        ));

        if (count($lines) < 2)
        {
            return null;
        }

        for ($headerIndex = 0; $headerIndex < count($lines) - 1; $headerIndex++)
        {
            $headerLine = $lines[$headerIndex];
            $parsedHeader = $this->tryParseContactHeaderLine($headerLine);
            if ($parsedHeader === null)
            {
                continue;
            }

            [$delimiter, $keys] = $parsedHeader;
            if (! $this->contactHeaderHasIdentifierColumn($keys))
            {
                return null;
            }

            $rows = [];
            for ($i = $headerIndex + 1; $i < count($lines); $i++)
            {
                $values = str_getcsv($lines[$i], $delimiter);
                $valueCount = count($values);
                $keyCount = count($keys);
                if ($valueCount < $keyCount)
                {
                    $values = array_pad($values, $keyCount, '');
                } elseif ($valueCount > $keyCount)
                {
                    $values = array_slice($values, 0, $keyCount);
                }

                $row = [];
                foreach ($keys as $idx => $key)
                {
                    if ($key === null)
                    {
                        continue;
                    }
                    $row[$key] = trim((string) ($values[$idx] ?? ''));
                }
                $rows[] = $row;
            }

            return ['rows' => $rows];
        }

        return null;
    }

    /**
     * @return array{0: string, 1: array<int, string|null>}|null
     */
    private function tryParseContactHeaderLine(string $line): ?array
    {
        $delimiter = $this->detectDelimiter($line);
        $headerCells = str_getcsv($line, $delimiter);
        $keys = [];
        foreach ($headerCells as $cell)
        {
            $keys[] = $this->contactHeaderToKey((string) $cell);
        }

        return [$delimiter, $keys];
    }

    /**
     * @param  array<int, string|null>  $keys
     */
    private function contactHeaderHasIdentifierColumn(array $keys): bool
    {
        foreach (['nombre', 'email', 'telefono'] as $need)
        {
            if (in_array($need, $keys, true))
            {
                return true;
            }
        }

        return false;
    }

    private function detectDelimiter(string $firstLine): string
    {
        $commas = substr_count($firstLine, ',');
        $semis = substr_count($firstLine, ';');

        return $semis > $commas ? ';' : ',';
    }

    private function contactHeaderToKey(string $raw): ?string
    {
        $n = $this->normalizeLabel($raw);

        return match (true)
        {
            in_array($n, ['nombre', 'name', 'contacto'], true) => 'nombre',
            in_array($n, ['apellido', 'apellidos', 'surname'], true) => 'apellido',
            in_array($n, ['email', 'correo', 'mail'], true) => 'email',
            in_array($n, ['telefono', 'tel', 'movil', 'móvil', 'phone', 'celular'], true) => 'telefono',
            in_array($n, ['empresa', 'cliente', 'company'], true) => 'empresa',
            in_array($n, ['nota', 'observaciones', 'comentario', 'comentarios'], true) => 'nota',
            default => null,
        };
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

    private function resolveDefaultContactStatusId(): int
    {
        $id = ContactStatus::query()->where('name', 'Lead')->value('id');
        if ($id !== null)
        {
            return (int) $id;
        }

        $fallback = ContactStatus::query()->orderBy('id')->value('id');

        return $fallback !== null ? (int) $fallback : 1;
    }

    private function resolveEnterpriseIdForLabel(int $teamId, string $label): ?int
    {
        $n = trim($label);
        if ($n === '')
        {
            return null;
        }

        $lower = mb_strtolower($n);

        $byName = Enterprise::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereRaw('LOWER(name) = ?', [$lower])
            ->orderBy('id')
            ->first();

        if ($byName !== null)
        {
            return (int) $byName->id;
        }

        $byCode = Enterprise::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->whereRaw('LOWER(code) = ?', [$lower])
            ->orderBy('id')
            ->first();

        return $byCode !== null ? (int) $byCode->id : null;
    }
}
