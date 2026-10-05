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

    public const DEFAULT_COUNTRY_ID = 32;

    /**
     * @var array<string, string>
     */
    private const CALLING_CODES = [
        'AR' => '54',
        'ES' => '34',
        'MX' => '52',
        'CL' => '56',
        'CO' => '57',
        'PE' => '51',
        'UY' => '598',
        'BR' => '55',
        'US' => '1',
        'CA' => '1',
        'FR' => '33',
        'IT' => '39',
        'DE' => '49',
        'GB' => '44',
        'PT' => '351',
        'PY' => '595',
        'BO' => '591',
        'EC' => '593',
        'VE' => '58',
        'PA' => '507',
        'CR' => '506',
    ];

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
     * Local numbers receive the calling code of the chosen country.
     * Argentina mobiles are stored as 549 plus the 10-digit national number.
     * Empty values, the text NULL and a lone + are ignored so they do not wipe a phone.
     */
    public function normalizePhone(mixed $phone, string $callingCode = '54'): ?string
    {
        if (! is_string($phone) && ! is_numeric($phone))
        {
            return null;
        }

        $raw = trim((string) $phone);
        if ($raw === '' || strcasecmp($raw, 'NULL') === 0 || $raw === '+' || $raw === '-')
        {
            return null;
        }

        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if (str_starts_with($digits, '00'))
        {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0'))
        {
            $digits = substr($digits, 1);
        }
        if ($digits === '')
        {
            return null;
        }

        if ($callingCode === '54')
        {
            return $this->normalizeArgentinePhone($digits);
        }

        if (str_starts_with($digits, $callingCode) && strlen($digits) <= 15)
        {
            return $digits;
        }

        if ($this->hasOtherCallingCode($digits, $callingCode) && strlen($digits) <= 15)
        {
            return $digits;
        }

        if ($callingCode === '34' && preg_match('/^\d{9}$/', $digits) === 1)
        {
            return $callingCode.$digits;
        }

        if (strlen($digits) >= 8 && strlen($digits) <= 11)
        {
            $withCode = $callingCode.$digits;

            return strlen($withCode) <= 15 ? $withCode : null;
        }

        return null;
    }

    private function normalizeArgentinePhone(string $digits): ?string
    {
        if (preg_match('/^549\d{10}$/', $digits) === 1)
        {
            return $digits;
        }

        if (preg_match('/^54911(\d{10})$/', $digits, $matches) === 1)
        {
            return '549'.$matches[1];
        }

        if (preg_match('/^54(\d{10})$/', $digits, $matches) === 1)
        {
            if (str_starts_with($matches[1], '9'))
            {
                return null;
            }

            return '549'.$matches[1];
        }

        if (preg_match('/^9\d{10}$/', $digits) === 1)
        {
            return '54'.$digits;
        }

        if (preg_match('/^15(\d{8})$/', $digits, $matches) === 1)
        {
            return '54911'.$matches[1];
        }

        if (preg_match('/^\d{10}$/', $digits) === 1)
        {
            return '549'.$digits;
        }

        if (preg_match('/^\d{12}$/', $digits) === 1)
        {
            return '549'.substr($digits, 0, 10);
        }

        if (preg_match('/^\d{8}$/', $digits) === 1)
        {
            return '54911'.$digits;
        }

        if (preg_match('/^34\d{9}$/', $digits) === 1)
        {
            return $digits;
        }

        return null;
    }

    private function hasOtherCallingCode(string $digits, string $callingCode): bool
    {
        $codes = array_values(self::CALLING_CODES);
        usort($codes, fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        foreach ($codes as $code)
        {
            if ($code === $callingCode || strlen($code) < 1)
            {
                continue;
            }

            if (str_starts_with($digits, $code) && strlen($digits) >= strlen($code) + 8)
            {
                return true;
            }
        }

        return false;
    }

    public function callingCodeForCountry(int $countryId): string
    {
        $code = strtoupper((string) DB::table('countries')->where('id', $countryId)->value('code'));

        return self::CALLING_CODES[$code] ?? '54';
    }

    /**
     * @return array{countries: list<array{id: int, name: string, code: string, calling_code: string}>, lists: list<array{id: int, name: string, color: ?string}>, default_country_id: int}
     */
    public function importOptions(int $teamId): array
    {
        $countries = DB::table('countries')
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(function (object $country): ?array
            {
                $code = strtoupper((string) $country->code);
                $callingCode = self::CALLING_CODES[$code] ?? null;
                if ($callingCode === null)
                {
                    return null;
                }

                return [
                    'id' => (int) $country->id,
                    'name' => (string) $country->name,
                    'code' => $code,
                    'calling_code' => $callingCode,
                ];
            })
            ->filter()
            ->values()
            ->all();

        usort($countries, function (array $left, array $right): int
        {
            $rank = ['AR' => 0, 'ES' => 1];

            return ($rank[$left['code']] ?? 9) <=> ($rank[$right['code']] ?? 9)
                ?: strcmp($left['name'], $right['name']);
        });

        $moduleId = Module::query()->where('key', 'contacts')->value('id');
        $lists = Category::query()
            ->where('team_id', $teamId)
            ->when($moduleId, fn ($query) => $query->where('module_id', $moduleId))
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'color' => MailerCategoryService::normalizeColor($category->color),
            ])
            ->values()
            ->all();

        return [
            'countries' => $countries,
            'lists' => $lists,
            'default_country_id' => $this->resolvedCountryId(self::DEFAULT_COUNTRY_ID),
        ];
    }

    /**
     * @param  list<int>  $categoryIds
     * @return list<int>
     */
    private function categoryIdsForTeam(int $teamId, array $categoryIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));
        if ($ids === [])
        {
            return [];
        }

        $moduleId = Module::query()->where('key', 'contacts')->value('id');

        return Category::query()
            ->where('team_id', $teamId)
            ->when($moduleId, fn ($query) => $query->where('module_id', $moduleId))
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function resolvedCountryId(int $countryId): int
    {
        $exists = DB::table('countries')->where('id', $countryId)->exists();
        if ($exists && isset(self::CALLING_CODES[strtoupper((string) DB::table('countries')->where('id', $countryId)->value('code'))]))
        {
            return $countryId;
        }

        if (DB::table('countries')->where('id', self::DEFAULT_COUNTRY_ID)->exists())
        {
            return self::DEFAULT_COUNTRY_ID;
        }

        return $this->defaultCountryId();
    }

    /**
     * @param  array<string, string>  $choices
     * @return array{created: int, updated: int, skipped: int, phones: int, errors: list<string>, duplicates: list<array{email: string, existing_phone: ?string, options: list<array{phone: string, raw: string, label: string, line: int}>}>}
     */
    public function preview(string $absolutePath, Team $team, array $choices = [], int $countryId = self::DEFAULT_COUNTRY_ID): array
    {
        $plan = $this->plan($absolutePath, $team, $choices, $countryId);
        unset($plan['groups']);

        return $plan;
    }

    /**
     * @param  array<string, string>  $choices  email => normalized phone, or "skip"
     * @param  list<int>  $categoryIds
     * @return array{created: int, updated: int, skipped: int, phones: int, errors: list<string>, duplicates: list<array{email: string, existing_phone: ?string, options: list<array{phone: string, raw: string, label: string, line: int}>}>}
     */
    public function import(string $absolutePath, Team $team, int $ownerId, array $choices = [], int $countryId = self::DEFAULT_COUNTRY_ID, array $categoryIds = []): array
    {
        $plan = $this->plan($absolutePath, $team, $choices, $countryId);
        if ($plan['duplicates'] !== [])
        {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => $plan['skipped'],
                'phones' => 0,
                'errors' => $plan['errors'],
                'duplicates' => $plan['duplicates'],
            ];
        }

        $created = 0;
        $updated = 0;
        $skipped = $plan['skipped'];
        $phones = 0;
        $errors = $plan['errors'];
        $limit = $team->getContactLimit();
        $used = $this->audienceCount((int) $team->id);
        $leadStatusId = (int) (ContactStatus::query()->where('name', 'Lead')->value('id') ?? 1);
        $language = $this->defaultLanguageCode();
        $country = $this->resolvedCountryId($countryId);
        $categoryIds = $this->categoryIdsForTeam((int) $team->id, $categoryIds);

        foreach ($plan['groups'] as $group)
        {
            $contact = $group['contact'];
            if ($contact === null && $used >= $limit)
            {
                $skipped++;
                $errors[] = __('Fila :line: se alcanzó el límite de suscriptores (:limit).', [
                    'line' => $group['line'],
                    'limit' => $limit,
                ]);

                continue;
            }

            if ($contact === null)
            {
                $contact = Contact::withoutGlobalScopes()->create([
                    'team_id' => $team->id,
                    'name' => $group['name'],
                    'surname' => $group['surname'],
                    'email' => $group['email'],
                    'phone' => $group['phone'],
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
                $payload = ['email' => $group['email'], 'country' => $country];
                if ($group['provided_name'] !== '')
                {
                    $payload['name'] = $group['name'];
                }
                if ($group['surname'] !== null)
                {
                    $payload['surname'] = $group['surname'];
                }
                if ($group['phone'] !== null)
                {
                    $payload['phone'] = $group['phone'];
                }
                $contact->fill($payload)->save();
                $updated++;
            }

            if ($group['phone'] !== null)
            {
                $phones++;
            }

            $attached = $categoryIds;
            if ($group['categories'] !== '')
            {
                $attached = array_values(array_unique(array_merge(
                    $attached,
                    $this->resolveCategoryIds((int) $team->id, $group['categories']),
                )));
            }
            if ($attached !== [])
            {
                $contact->categories()->syncWithoutDetaching($attached);
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'phones' => $phones,
            'errors' => $errors,
            'duplicates' => [],
        ];
    }

    /**
     * @param  array<string, string>  $choices
     * @return array{created: int, updated: int, skipped: int, phones: int, errors: list<string>, duplicates: list<array{email: string, existing_phone: ?string, options: list<array{phone: string, raw: string, label: string, line: int}>}>, groups: list<array{email: string, line: int, name: string, provided_name: string, surname: ?string, phone: ?string, categories: string, contact: ?Contact}>}
     */
    private function plan(string $absolutePath, Team $team, array $choices, int $countryId = self::DEFAULT_COUNTRY_ID): array
    {
        $empty = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'phones' => 0,
            'errors' => [],
            'duplicates' => [],
            'groups' => [],
        ];
        $rows = $this->readRows($absolutePath);
        if ($rows === [])
        {
            $empty['errors'] = [__('El archivo no tiene filas de datos.')];

            return $empty;
        }

        $missingHeaders = array_diff(self::REQUIRED_COLUMNS, array_keys($rows[0]['values']));
        if ($missingHeaders !== [])
        {
            $empty['skipped'] = count($rows);
            $empty['errors'] = [__('Faltan columnas obligatorias: :columns', ['columns' => implode(', ', $missingHeaders)])];

            return $empty;
        }

        $grouped = [];
        $skipped = 0;
        $errors = [];

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

            $rawPhone = trim((string) ($row['values']['phone'] ?? ''));
            $phone = $this->normalizePhone($rawPhone, $this->callingCodeForCountry($countryId));
            if ($this->phoneWasProvided($rawPhone) && $phone === null)
            {
                $errors[] = __('Fila :line: el teléfono :phone no se pudo normalizar.', [
                    'line' => $line,
                    'phone' => $rawPhone,
                ]);
            }

            if (! isset($grouped[$email]))
            {
                $providedName = trim((string) ($row['values']['name'] ?? ''));
                $grouped[$email] = [
                    'email' => $email,
                    'line' => $line,
                    'provided_name' => $providedName,
                    'surname' => trim((string) ($row['values']['surname'] ?? '')) ?: null,
                    'categories' => [],
                    'phones' => [],
                ];
            }

            if ($grouped[$email]['provided_name'] === '')
            {
                $providedName = trim((string) ($row['values']['name'] ?? ''));
                if ($providedName !== '')
                {
                    $grouped[$email]['provided_name'] = $providedName;
                }
            }
            if ($grouped[$email]['surname'] === null)
            {
                $surname = trim((string) ($row['values']['surname'] ?? '')) ?: null;
                if ($surname !== null)
                {
                    $grouped[$email]['surname'] = $surname;
                }
            }

            $category = trim((string) ($row['values']['categories'] ?? ''));
            if ($category !== '')
            {
                $grouped[$email]['categories'][] = $category;
            }

            if ($phone === null)
            {
                continue;
            }

            $label = trim((string) ($row['values']['empresa'] ?? ''));
            if ($label === '')
            {
                $label = trim((string) ($row['values']['name'] ?? ''));
            }
            if ($label === '')
            {
                $label = __('Fila :line', ['line' => $line]);
            }

            $already = false;
            foreach ($grouped[$email]['phones'] as $option)
            {
                if ($option['phone'] === $phone)
                {
                    $already = true;
                    break;
                }
            }
            if ($already)
            {
                continue;
            }

            $grouped[$email]['phones'][] = [
                'phone' => $phone,
                'raw' => $rawPhone,
                'label' => $label,
                'line' => $line,
            ];
        }

        $emails = array_keys($grouped);
        $existing = $emails === []
            ? collect()
            : Contact::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->whereRaw('LOWER(email) IN ('.implode(',', array_fill(0, count($emails), '?')).')', $emails)
                ->get()
                ->keyBy(fn (Contact $contact): string => Str::lower((string) $contact->email));

        $created = 0;
        $updated = 0;
        $phones = 0;
        $duplicates = [];
        $groups = [];

        foreach ($grouped as $email => $group)
        {
            $contact = $existing->get($email);
            $options = $group['phones'];
            $phone = null;

            if (count($options) > 1)
            {
                $choice = $choices[$email] ?? null;
                if ($choice === null || $choice === '')
                {
                    $duplicates[] = [
                        'email' => $email,
                        'existing_phone' => $contact?->phone,
                        'options' => $options,
                    ];

                    continue;
                }

                if ($choice === 'skip')
                {
                    $phone = null;
                } elseif (collect($options)->contains(fn (array $option): bool => $option['phone'] === $choice))
                {
                    $phone = $choice;
                } else
                {
                    $errors[] = __('El teléfono elegido para :email no está entre las opciones.', ['email' => $email]);
                    $duplicates[] = [
                        'email' => $email,
                        'existing_phone' => $contact?->phone,
                        'options' => $options,
                    ];

                    continue;
                }
            } else
            {
                $phone = $options[0]['phone'] ?? null;
            }

            if ($contact === null)
            {
                $created++;
            } else
            {
                $updated++;
            }
            if ($phone !== null)
            {
                $phones++;
            }

            $providedName = $group['provided_name'];
            $groups[] = [
                'email' => $email,
                'line' => $group['line'],
                'name' => $providedName !== ''
                    ? $providedName
                    : Str::of(Str::before($email, '@'))->replace(['.', '_', '-'], ' ')->title()->toString(),
                'provided_name' => $providedName,
                'surname' => $group['surname'],
                'phone' => $phone,
                'categories' => implode('|', $group['categories']),
                'contact' => $contact,
            ];
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'phones' => $phones,
            'errors' => $errors,
            'duplicates' => $duplicates,
            'groups' => $groups,
        ];
    }

    private function phoneWasProvided(string $raw): bool
    {
        $raw = trim($raw);

        return $raw !== '' && strcasecmp($raw, 'NULL') !== 0 && $raw !== '+' && $raw !== '-';
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
        $contents = file_get_contents($absolutePath);
        if ($contents === false)
        {
            return [];
        }

        if (str_starts_with($contents, "\xEF\xBB\xBF"))
        {
            $contents = substr($contents, 3);
        }

        if (! mb_check_encoding($contents, 'UTF-8'))
        {
            $converted = iconv('WINDOWS-1252', 'UTF-8//IGNORE', $contents);
            if ($converted !== false)
            {
                $contents = $converted;
            }
        }

        $handle = fopen('php://temp', 'r+');
        if ($handle === false)
        {
            return [];
        }

        fwrite($handle, $contents);
        rewind($handle);

        $delimiter = $this->detectDelimiter($contents);
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
                $values[$header] = $this->repairText(isset($raw[$index]) ? trim((string) $raw[$index]) : '');
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

    private function detectDelimiter(string $contents): string
    {
        $firstLine = str_contains($contents, "\n") ? strstr($contents, "\n", true) : $contents;
        $firstLine = str_replace("\r", '', (string) $firstLine);

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }

    /**
     * Names like Cervecer√≠a are UTF-8 bytes that were read as Mac Roman and saved again.
     * √≠ is í and √≥ is ó. √Ä is the same damage on Á, stored one step off (Ä instead of Å).
     */
    private function repairText(string $value): string
    {
        $value = str_replace("\u{00A0}", ' ', $value);
        if (! str_contains($value, '√'))
        {
            return $value;
        }

        $repaired = preg_replace_callback('/√./u', function (array $match): string
        {
            $chunk = $match[0];
            if ($chunk === "\u{221A}\u{00C4}")
            {
                return 'Á';
            }

            $bytes = iconv('UTF-8', 'MACINTOSH', $chunk);
            if ($bytes !== false && $bytes !== '' && mb_check_encoding($bytes, 'UTF-8'))
            {
                return $bytes;
            }

            return $chunk;
        }, $value);

        return is_string($repaired) ? $repaired : $value;
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
