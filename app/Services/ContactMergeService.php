<?php

namespace App\Services;

use App\Exceptions\ContactMergeException;
use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\EnterpriseDepartment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ContactMergeService
{
    /**
     * @return array{
     *     blocked: bool,
     *     message: string,
     *     survivor_id: int,
     *     source_id: int,
     *     survivor_name: string,
     *     source_name: string,
     *     lines: list<string>
     * }
     */
    public function preview(Contact $current, Contact $other): array
    {
        try
        {
            [$survivor, $source] = $this->pair($current, $other);
        } catch (ContactMergeException $exception)
        {
            return [
                'blocked' => true,
                'message' => $exception->getMessage(),
                'survivor_id' => $current->id,
                'source_id' => $other->id,
                'survivor_name' => $this->label($current),
                'source_name' => $this->label($other),
                'lines' => [],
            ];
        }

        return [
            'blocked' => false,
            'message' => 'Se conserva '.$this->label($survivor).'. '.$this->label($source).' se archiva.',
            'survivor_id' => $survivor->id,
            'source_id' => $source->id,
            'survivor_name' => $this->label($survivor),
            'source_name' => $this->label($source),
            'lines' => array_merge(
                $this->enterpriseLines($survivor, $source),
                $this->fieldLines($survivor, $source),
                $this->categoryLines($survivor, $source),
            ),
        ];
    }

    public function merge(Contact $current, Contact $other): Contact
    {
        [$survivor, $source] = $this->pair($current, $other);

        DB::transaction(function () use ($survivor, $source): void
        {
            $this->moveEnterpriseLinks($survivor, $source);
            $this->repointSuperiors($survivor, $source);
            $this->moveOwnedRecords($source->id, $survivor->id);
            $this->fillEmptyFields($source, $survivor);
            $this->archive($source, $survivor);
            $survivor->save();
        });

        return $survivor->fresh() ?? $survivor;
    }

    /**
     * The contact being viewed stays. Two different linked users cannot be merged.
     *
     * @return array{0: Contact, 1: Contact}
     */
    private function pair(Contact $current, Contact $other): array
    {
        if ((int) $current->id === (int) $other->id)
        {
            throw new ContactMergeException('Elegí otro contacto.');
        }

        if ((int) $current->team_id !== (int) $other->team_id)
        {
            throw new ContactMergeException('Los contactos tienen que ser del mismo equipo.');
        }

        if ($current->user_id !== null && $other->user_id !== null && (int) $current->user_id !== (int) $other->user_id)
        {
            $users = User::query()
                ->whereIn('id', [$current->user_id, $other->user_id])
                ->get()
                ->keyBy('id');

            throw new ContactMergeException(
                'No se pueden fusionar porque cada contacto entra con un acceso distinto. '
                .$this->label($current).' entra como '.$this->userLabel($users->get($current->user_id)).' y '
                .$this->label($other).' entra como '.$this->userLabel($users->get($other->user_id)).'. '
                .'Un acceso solo puede quedar en un contacto: fusionarlos dejaría uno de los dos sin ficha.',
            );
        }

        return [$current, $other];
    }

    /**
     * @return list<string>
     */
    private function enterpriseLines(Contact $survivor, Contact $source): array
    {
        $lines = [];
        foreach ($this->enterpriseActions($survivor, $source) as $action)
        {
            $lines[] = $action['line'];
        }

        if ($lines === [])
        {
            $lines[] = 'Ninguno de los dos está vinculado a una empresa.';
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function fieldLines(Contact $survivor, Contact $source): array
    {
        $lines = [];
        if (! $this->isBlank($survivor->name) && ! $this->isBlank($source->name) && trim((string) $survivor->name) !== trim((string) $source->name))
        {
            $lines[] = 'Nombre: se conserva '.$this->displayValue($survivor->name).'. El otro tiene '.$this->displayValue($source->name).'.';
        }

        foreach ([
            'surname' => 'Apellido',
            'email' => 'Email',
            'phone' => 'Teléfono',
            'birthday' => 'Fecha de nacimiento',
            'profile' => 'Perfil',
            'country' => 'País',
            'language' => 'Idioma',
        ] as $field => $label)
        {
            $line = $this->valueLine($label, $survivor->{$field}, $source->{$field});
            if ($line !== null)
            {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function categoryLines(Contact $survivor, Contact $source): array
    {
        if (! Schema::hasTable('contact_category') || ! Schema::hasTable('categories'))
        {
            return [];
        }

        $keptIds = DB::table('contact_category')
            ->where('contact_id', $survivor->id)
            ->pluck('category_id');
        $names = DB::table('contact_category')
            ->join('categories', 'categories.id', '=', 'contact_category.category_id')
            ->where('contact_category.contact_id', $source->id)
            ->whereNotIn('contact_category.category_id', $keptIds)
            ->orderBy('categories.name')
            ->pluck('categories.name');

        $lines = [];
        foreach ($names as $name)
        {
            $lines[] = 'Categoría: pasa '.$name.'.';
        }

        return $lines;
    }

    private function valueLine(string $label, mixed $kept, mixed $incoming): ?string
    {
        if ($this->isBlank($incoming))
        {
            return null;
        }

        $incomingText = $this->displayValue($incoming);
        if ($this->isBlank($kept))
        {
            return $label.': se completa con '.$incomingText.'.';
        }

        $keptText = $this->displayValue($kept);
        if ($keptText === $incomingText)
        {
            return null;
        }

        return $label.': se conserva '.$keptText.'. El otro tiene '.$incomingText.'.';
    }

    private function displayValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface)
        {
            return $value->format('d/m/Y');
        }

        $text = trim((string) $value);
        if (mb_strlen($text) > 120)
        {
            return mb_substr($text, 0, 117).'...';
        }

        return $text;
    }

    private function moveEnterpriseLinks(Contact $survivor, Contact $source): void
    {
        foreach ($this->enterpriseActions($survivor, $source) as $action)
        {
            if ($action['type'] === 'move')
            {
                DB::table('contact_enterprise')
                    ->where('id', $action['row_id'])
                    ->update([
                        'contact_id' => $survivor->id,
                        'superior_id' => $action['superior_id'],
                        'updated_at' => now(),
                    ]);

                continue;
            }

            if ($action['type'] === 'fill')
            {
                DB::table('contact_enterprise')
                    ->where('id', $action['row_id'])
                    ->update([
                        'position' => $action['position'],
                        'department_id' => $action['department_id'],
                        'superior_id' => $action['superior_id'],
                        'updated_at' => now(),
                    ]);
            }

            DB::table('contact_enterprise')->where('id', $action['source_row_id'])->delete();
        }
    }

    /**
     * One row per enterprise. A different company keeps its own role.
     * The same company keeps the role already on the contact being viewed, and only fills blanks.
     *
     * @return list<array<string, mixed>>
     */
    private function enterpriseActions(Contact $survivor, Contact $source): array
    {
        $survivorRows = DB::table('contact_enterprise')
            ->where('contact_id', $survivor->id)
            ->orderBy('id')
            ->get()
            ->keyBy('enterprise_id');
        $sourceRows = DB::table('contact_enterprise')
            ->where('contact_id', $source->id)
            ->orderBy('id')
            ->get();

        $enterpriseIds = $survivorRows->pluck('enterprise_id')
            ->merge($sourceRows->pluck('enterprise_id'))
            ->unique()
            ->all();
        $names = Enterprise::withoutGlobalScopes()
            ->whereIn('id', $enterpriseIds)
            ->pluck('name', 'id');
        $departmentIds = $survivorRows->pluck('department_id')
            ->merge($sourceRows->pluck('department_id'))
            ->filter()
            ->unique()
            ->all();
        $departments = $departmentIds === []
            ? collect()
            : EnterpriseDepartment::query()->whereIn('id', $departmentIds)->pluck('name', 'id');

        $actions = [];
        foreach ($sourceRows as $sourceRow)
        {
            $enterpriseName = (string) ($names[$sourceRow->enterprise_id] ?? 'Empresa');
            $existing = $survivorRows->get($sourceRow->enterprise_id);

            if ($existing === null)
            {
                $superiorId = (int) $sourceRow->superior_id === (int) $source->id
                    ? $survivor->id
                    : $sourceRow->superior_id;
                $actions[] = [
                    'type' => 'move',
                    'row_id' => $sourceRow->id,
                    'source_row_id' => $sourceRow->id,
                    'superior_id' => $superiorId,
                    'line' => 'Pasa a '.$enterpriseName.' como '.$this->roleText($sourceRow, $departments).'.',
                ];

                continue;
            }

            $filled = $this->filledRole($existing, $sourceRow, $source, $survivor);
            if ($filled['changed'])
            {
                $actions[] = [
                    'type' => 'fill',
                    'row_id' => $existing->id,
                    'source_row_id' => $sourceRow->id,
                    'position' => $filled['position'],
                    'department_id' => $filled['department_id'],
                    'superior_id' => $filled['superior_id'],
                    'line' => 'En '.$enterpriseName.' se completa el rol: '.$this->roleText((object) $filled, $departments).'.',
                ];

                continue;
            }

            $actions[] = [
                'type' => 'keep',
                'row_id' => $existing->id,
                'source_row_id' => $sourceRow->id,
                'line' => 'Ya está en '.$enterpriseName.' como '.$this->roleText($existing, $departments).'. Se conserva ese rol.',
            ];
        }

        return $actions;
    }

    /**
     * @return array{changed: bool, position: ?string, department_id: ?int, superior_id: ?int}
     */
    private function filledRole(object $existing, object $sourceRow, Contact $source, Contact $survivor): array
    {
        $position = $this->isBlank($existing->position) && ! $this->isBlank($sourceRow->position)
            ? $sourceRow->position
            : $existing->position;
        $departmentId = $existing->department_id === null && $sourceRow->department_id !== null
            ? (int) $sourceRow->department_id
            : ($existing->department_id === null ? null : (int) $existing->department_id);
        $superiorId = $existing->superior_id;
        if ($superiorId === null && $sourceRow->superior_id !== null)
        {
            $superiorId = (int) $sourceRow->superior_id === (int) $source->id
                ? $survivor->id
                : (int) $sourceRow->superior_id;
        }

        $changed = (string) $position !== (string) $existing->position
            || (int) ($departmentId ?? 0) !== (int) ($existing->department_id ?? 0)
            || (int) ($superiorId ?? 0) !== (int) ($existing->superior_id ?? 0);

        return [
            'changed' => $changed,
            'position' => $position,
            'department_id' => $departmentId,
            'superior_id' => $superiorId === null ? null : (int) $superiorId,
        ];
    }

    private function repointSuperiors(Contact $survivor, Contact $source): void
    {
        DB::table('contact_enterprise')
            ->where('superior_id', $source->id)
            ->update([
                'superior_id' => $survivor->id,
                'updated_at' => now(),
            ]);
    }

    private function moveOwnedRecords(int $sourceId, int $survivorId): void
    {
        foreach ([
            'opportunities',
            'contact_interactions',
            'contact_sentiment_histories',
            'notifications',
            'orders',
            'communications',
            'site_assistant_messages',
            'contact_actions',
            'contact_portfolios',
        ] as $table)
        {
            $this->reassign($table, $sourceId, $survivorId, null);
        }

        foreach (['contact_astral_profiles', 'contact_weekly_availability', 'list60'] as $table)
        {
            $this->keepSurvivorSingleton($table, $sourceId, $survivorId);
        }

        $this->reassign('contact_absences', $sourceId, $survivorId, ['absence_date']);
        $this->reassign('contact_category', $sourceId, $survivorId, ['category_id']);
        $this->reassign('contact_sources', $sourceId, $survivorId, ['source_id', 'value']);
        $this->reassign('contact_softwares', $sourceId, $survivorId, ['software_id']);
        $this->reassign('contact_topics', $sourceId, $survivorId, ['topic_id']);
        $this->reassign('contact_project', $sourceId, $survivorId, ['project_id']);
        $this->reassign('contact_language_variants', $sourceId, $survivorId, ['source_language_code', 'target_language_code']);
        $this->reassign('calendar_event_contact', $sourceId, $survivorId, ['calendar_event_id']);
        $this->reassign('contact_sync_mappings', $sourceId, $survivorId, ['external_account_id']);
        $this->reassign('contact_fare', $sourceId, $survivorId, ['fare_id', 'source_language_code', 'target_language_code']);

        $deliveryIdentity = ['message_id'];
        if (Schema::hasTable('message_deliveries') && Schema::hasColumn('message_deliveries', 'campaign_id'))
        {
            $deliveryIdentity[] = 'campaign_id';
        }
        $this->reassign('message_deliveries', $sourceId, $survivorId, $deliveryIdentity);
    }

    /**
     * @param  list<string>|null  $identity
     */
    private function reassign(string $table, int $sourceId, int $survivorId, ?array $identity): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'contact_id'))
        {
            return;
        }

        if ($identity === null)
        {
            DB::table($table)->where('contact_id', $sourceId)->update(['contact_id' => $survivorId]);

            return;
        }

        $hasId = Schema::hasColumn($table, 'id');
        $rows = DB::table($table)->where('contact_id', $sourceId)->get();
        foreach ($rows as $row)
        {
            $match = DB::table($table)->where('contact_id', $survivorId);
            $this->applyIdentity($match, $table, $row, $identity);

            $rowQuery = $hasId
                ? DB::table($table)->where('id', $row->id)
                : $this->applyIdentity(DB::table($table)->where('contact_id', $sourceId), $table, $row, $identity);

            if ($match->exists())
            {
                $rowQuery->delete();

                continue;
            }

            $rowQuery->update(['contact_id' => $survivorId]);
        }
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  list<string>  $identity
     */
    private function applyIdentity($query, string $table, object $row, array $identity): mixed
    {
        foreach ($identity as $column)
        {
            if (! Schema::hasColumn($table, $column))
            {
                continue;
            }

            if ($row->{$column} === null)
            {
                $query->whereNull($column);
            } else
            {
                $query->where($column, $row->{$column});
            }
        }

        return $query;
    }

    private function keepSurvivorSingleton(string $table, int $sourceId, int $survivorId): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'contact_id'))
        {
            return;
        }

        if (DB::table($table)->where('contact_id', $survivorId)->exists())
        {
            DB::table($table)->where('contact_id', $sourceId)->delete();

            return;
        }

        DB::table($table)->where('contact_id', $sourceId)->update(['contact_id' => $survivorId]);
    }

    private function fillEmptyFields(Contact $source, Contact $survivor): void
    {
        foreach ([
            'surname',
            'email',
            'phone',
            'birthday',
            'profile',
            'country',
            'language',
            'responsible_id',
            'status_id',
            'source_id',
            'valoration_id',
        ] as $field)
        {
            if ($this->isBlank($survivor->{$field}) && ! $this->isBlank($source->{$field}))
            {
                $survivor->{$field} = $source->{$field};
            }
        }

        if ($survivor->user_id === null && $source->user_id !== null)
        {
            $survivor->user_id = $source->user_id;
            $source->user_id = null;
            $source->save();
        }

        $linkedEnterprises = DB::table('contact_enterprise')
            ->where('contact_id', $survivor->id)
            ->pluck('enterprise_id');
        if ($this->isBlank($survivor->current_enterprise_id) && $linkedEnterprises->isNotEmpty())
        {
            $preferred = (int) $source->current_enterprise_id;
            $survivor->current_enterprise_id = $linkedEnterprises->contains($preferred)
                ? $preferred
                : (int) $linkedEnterprises->first();
        }

        $survivorData = (array) ($survivor->data ?? []);
        foreach ((array) ($source->data ?? []) as $key => $value)
        {
            if (! array_key_exists($key, $survivorData) || $this->isBlank($survivorData[$key]))
            {
                $survivorData[$key] = $value;
            }
        }

        $survivor->data = (object) $survivorData;
    }

    private function archive(Contact $source, Contact $survivor): void
    {
        $data = (array) ($source->data ?? []);
        $data['merged_into_contact_id'] = $survivor->id;
        $data['merged_at'] = now()->toIso8601String();
        $source->data = (object) $data;
        $source->save();
        $source->delete();
    }

    private function userLabel(?User $user): string
    {
        if ($user === null)
        {
            return 'un usuario que ya no existe';
        }

        $name = trim((string) $user->name);
        $email = trim((string) $user->email);
        if ($name !== '' && $email !== '')
        {
            return $name.' ('.$email.')';
        }

        return $email !== '' ? $email : ($name !== '' ? $name : 'Usuario '.$user->id);
    }

    private function label(Contact $contact): string
    {
        $name = trim((string) $contact->name.' '.(string) ($contact->surname ?? ''));

        return $name !== '' ? $name : 'Contacto '.$contact->id;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $departments
     */
    private function roleText(object $row, $departments): string
    {
        $parts = [];
        $position = trim((string) ($row->position ?? ''));
        if ($position !== '')
        {
            $parts[] = $position;
        }

        $departmentId = (int) ($row->department_id ?? 0);
        if ($departmentId > 0 && $departments->has($departmentId))
        {
            $parts[] = (string) $departments->get($departmentId);
        }

        return $parts === [] ? 'sin cargo' : implode(', ', $parts);
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
