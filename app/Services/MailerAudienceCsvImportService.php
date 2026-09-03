<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Module;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MailerAudienceCsvImportService
{
    public const REQUIRED_COLUMNS = ['email'];

    public const OPTIONAL_COLUMNS = ['name', 'surname', 'phone', 'categories'];

    /**
     * @var array<string, string>
     */
    private const HEADER_ALIASES = [
        'correo' => 'email',
        'mail' => 'email',
        'e_mail' => 'email',
        'nombre' => 'name',
        'apellido' => 'surname',
        'apellidos' => 'surname',
        'telefono' => 'phone',
        'tel' => 'phone',
        'celular' => 'phone',
        'mobile' => 'phone',
        'categoria' => 'categories',
        'categorias' => 'categories',
        'lista' => 'categories',
        'listas' => 'categories',
    ];

    public function templateContents(): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, array_merge(self::REQUIRED_COLUMNS, self::OPTIONAL_COLUMNS));
        fputcsv($handle, ['lucia.garcia@cliente.com', 'Lucía', 'García', '+34600111222', 'Newsletter']);
        fputcsv($handle, ['martin.perez@cliente.com', 'Martín', 'Pérez', '+34600999888', 'Newsletter|VIP']);
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    public function audienceCount(int $teamId): int
    {
        return Contact::query()
            ->where('team_id', $teamId)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->count();
    }

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function import(string $absolutePath, Team $team, int $ownerId): array
    {
        $rows = $this->readRows($absolutePath);

        if ($rows === [])
        {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [__('El archivo no tiene filas de datos.')]];
        }

        $missingHeaders = array_diff(self::REQUIRED_COLUMNS, array_keys($rows[0]['values']));
        if ($missingHeaders !== [])
        {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => count($rows),
                'errors' => [__('Faltan columnas obligatorias: :columns', ['columns' => implode(', ', $missingHeaders)])],
            ];
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $seen = [];
        $limit = $team->getContactLimit();
        $used = $this->audienceCount((int) $team->id);
        $leadStatusId = (int) (ContactStatus::query()->where('name', 'Lead')->value('id') ?? 1);
        $language = $this->defaultLanguageCode();
        $country = $this->defaultCountryId();

        foreach ($rows as $row)
        {
            $email = Str::lower(trim((string) ($row['values']['email'] ?? '')));
            $line = $row['line'];

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL))
            {
                $skipped++;
                $errors[] = __('Fila :line: el email no es válido.', ['line' => $line]);

                continue;
            }

            if (isset($seen[$email]))
            {
                $skipped++;
                $errors[] = __('Fila :line: email repetido en el archivo.', ['line' => $line]);

                continue;
            }

            $seen[$email] = true;

            $contact = Contact::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();

            if ($contact === null && $used >= $limit)
            {
                $skipped++;
                $errors[] = __('Fila :line: se alcanzó el límite de suscriptores (:limit).', [
                    'line' => $line,
                    'limit' => $limit,
                ]);

                continue;
            }

            $providedName = trim((string) ($row['values']['name'] ?? ''));
            $surname = trim((string) ($row['values']['surname'] ?? '')) ?: null;
            $phone = trim((string) ($row['values']['phone'] ?? '')) ?: null;
            $name = $providedName !== ''
                ? $providedName
                : Str::of(Str::before($email, '@'))->replace(['.', '_', '-'], ' ')->title()->toString();

            if ($contact === null)
            {
                $contact = Contact::withoutGlobalScopes()->create([
                    'team_id' => $team->id,
                    'name' => $name,
                    'surname' => $surname,
                    'email' => $email,
                    'phone' => $phone,
                    'language' => $language,
                    'country' => $country,
                    'creator_id' => $ownerId,
                    'responsible_id' => $ownerId,
                    'status_id' => $leadStatusId,
                ]);
                $used++;
                $created++;
            } else
            {
                $payload = ['email' => $email];
                if ($providedName !== '')
                {
                    $payload['name'] = $providedName;
                }
                if ($surname !== null)
                {
                    $payload['surname'] = $surname;
                }
                if ($phone !== null)
                {
                    $payload['phone'] = $phone;
                }
                $contact->fill($payload)->save();
                $updated++;
            }

            if (array_key_exists('categories', $row['values']))
            {
                $categoryIds = $this->resolveCategoryIds((int) $team->id, (string) $row['values']['categories']);
                if ($categoryIds !== [])
                {
                    $contact->categories()->syncWithoutDetaching($categoryIds);
                }
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * @return list<int>
     */
    private function resolveCategoryIds(int $teamId, string $raw): array
    {
        $names = preg_split('/[|,]/', $raw) ?: [];
        $ids = [];

        foreach ($names as $name)
        {
            $name = trim((string) $name);
            if ($name === '')
            {
                continue;
            }

            $category = $this->findOrCreateList($teamId, $name);
            if ($category !== null)
            {
                $ids[] = (int) $category->id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function findOrCreateList(int $teamId, string $name): ?Category
    {
        $moduleId = Module::query()->where('key', 'contacts')->value('id');
        if (! $moduleId)
        {
            return null;
        }

        $normalized = mb_strtolower($name);
        $existing = Category::query()
            ->where('team_id', $teamId)
            ->where('module_id', $moduleId)
            ->whereNull('deleted_at')
            ->get()
            ->first(fn (Category $category): bool => mb_strtolower(trim((string) $category->name)) === $normalized);

        if ($existing)
        {
            return $existing;
        }

        return Category::query()->create([
            'name' => $name,
            'module_id' => $moduleId,
            'team_id' => $teamId,
            'parent_id' => null,
            'order' => 0,
            'status' => 1,
        ]);
    }

    /**
     * @return list<array{line: int, values: array<string, string>}>
     */
    private function readRows(string $absolutePath): array
    {
        $handle = fopen($absolutePath, 'r');
        if ($handle === false)
        {
            return [];
        }

        $delimiter = $this->detectDelimiter($absolutePath);
        $headers = null;
        $rows = [];
        $line = 0;

        while (($raw = fgetcsv($handle, 0, $delimiter)) !== false)
        {
            $line++;

            if ($raw === [null] || $raw === false)
            {
                continue;
            }

            if ($headers === null)
            {
                $headers = $this->normalizeHeaders($raw);

                continue;
            }

            if ($this->isBlankRow($raw))
            {
                continue;
            }

            $values = [];
            foreach ($headers as $index => $header)
            {
                if ($header === '')
                {
                    continue;
                }
                $values[$header] = isset($raw[$index]) ? trim((string) $raw[$index]) : '';
            }

            $rows[] = ['line' => $line, 'values' => $values];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<string|null>  $raw
     * @return list<string>
     */
    private function normalizeHeaders(array $raw): array
    {
        $headers = [];
        foreach ($raw as $index => $value)
        {
            $header = (string) $value;
            if ($index === 0)
            {
                $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
            }

            $header = Str::of($header)->trim()->lower()->ascii()->replace([' ', '-'], '_')->toString();
            $header = preg_replace('/[^a-z0-9_]/', '', $header) ?? $header;
            $headers[] = self::HEADER_ALIASES[$header] ?? $header;
        }

        return $headers;
    }

    private function detectDelimiter(string $absolutePath): string
    {
        $handle = fopen($absolutePath, 'r');
        if ($handle === false)
        {
            return ',';
        }

        $firstLine = (string) fgets($handle);
        fclose($handle);

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }

    /**
     * @param  list<string|null>  $raw
     */
    private function isBlankRow(array $raw): bool
    {
        foreach ($raw as $value)
        {
            if (trim((string) $value) !== '')
            {
                return false;
            }
        }

        return true;
    }

    private function defaultCountryId(): int
    {
        return (int) (DB::table('countries')->where('id', 724)->value('id')
            ?? DB::table('countries')->value('id')
            ?? 724);
    }

    private function defaultLanguageCode(): string
    {
        return (string) (DB::table('languages')->where('code', 'es')->value('code')
            ?? DB::table('languages')->value('code')
            ?? 'es');
    }
}
