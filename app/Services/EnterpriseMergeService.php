<?php

namespace App\Services;

use App\Exceptions\EnterpriseMergeException;
use App\Models\BillingAffiliateCommission;
use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\EnterpriseBillingAddress;
use App\Models\FiscalCustomerMapping;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Service;
use App\Models\TeamPassword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class EnterpriseMergeService
{
    /**
     * @return array{
     *     blocked: bool,
     *     message: string,
     *     survivor_id: int,
     *     source_id: int,
     *     survivor_name: string,
     *     source_name: string,
     *     survivor_code: ?string,
     *     lines: list<string>
     * }
     */
    public function preview(Enterprise $current, Enterprise $other): array
    {
        try
        {
            [$survivor, $source] = $this->pair($current, $other);
        } catch (EnterpriseMergeException $exception)
        {
            return [
                'blocked' => true,
                'message' => $exception->getMessage(),
                'survivor_id' => $current->id,
                'source_id' => $other->id,
                'survivor_name' => $current->name,
                'source_name' => $other->name,
                'survivor_code' => $current->getStripeCustomerId(),
                'lines' => [],
            ];
        }

        $lines = $this->summaryLines($source, $survivor);
        $code = $survivor->getStripeCustomerId();
        $kept = $code
            ? $survivor->name.' ('.$code.')'
            : $survivor->name;

        return [
            'blocked' => false,
            'message' => 'Se conserva '.$kept.'. '.$source->name.' se archiva.',
            'survivor_id' => $survivor->id,
            'source_id' => $source->id,
            'survivor_name' => $survivor->name,
            'source_name' => $source->name,
            'survivor_code' => $code,
            'lines' => $lines,
        ];
    }

    public function merge(Enterprise $current, Enterprise $other): Enterprise
    {
        [$survivor, $source] = $this->pair($current, $other);

        DB::transaction(function () use ($survivor, $source): void
        {
            $this->moveRecords($source, $survivor);
            $this->fillEmptyFields($source, $survivor);
            $this->archive($source, $survivor);
            $survivor->save();
        });

        return $survivor->fresh() ?? $survivor;
    }

    /**
     * The enterprise with a Stripe customer code stays. If neither has one, the record being viewed stays.
     *
     * @return array{0: Enterprise, 1: Enterprise}
     */
    private function pair(Enterprise $current, Enterprise $other): array
    {
        if ((int) $current->id === (int) $other->id)
        {
            throw new EnterpriseMergeException('Elegí otra empresa.');
        }

        if ((int) $current->team_id !== (int) $other->team_id)
        {
            throw new EnterpriseMergeException('Las empresas tienen que ser del mismo equipo.');
        }

        $currentStripe = $current->getStripeCustomerId();
        $otherStripe = $other->getStripeCustomerId();

        if ($currentStripe !== null && $otherStripe !== null && $currentStripe !== $otherStripe)
        {
            throw new EnterpriseMergeException('Las dos empresas tienen un cliente de Stripe distinto. No se pueden fusionar.');
        }

        if ($otherStripe !== null && $currentStripe === null)
        {
            return [$other, $current];
        }

        return [$current, $other];
    }

    /**
     * @return list<string>
     */
    private function summaryLines(Enterprise $source, Enterprise $survivor): array
    {
        $billing = $this->billingPlan($source, $survivor);
        $contactIds = DB::table('contact_enterprise')
            ->where('enterprise_id', $source->id)
            ->pluck('contact_id');
        $alreadyLinked = DB::table('contact_enterprise')
            ->where('enterprise_id', $survivor->id)
            ->whereIn('contact_id', $contactIds)
            ->count();
        $contactsToMove = $contactIds->count() - $alreadyLinked;

        $lines = array_values(array_filter([
            $this->line($this->countOwned(Project::class, $source->id), 'proyecto', 'proyectos'),
            $this->line($this->countOwned(Service::class, $source->id), 'servicio', 'servicios'),
            $this->line($this->countOwned(Invoice::class, $source->id), 'factura', 'facturas'),
            $this->line($this->countOwned(Payment::class, $source->id), 'pago', 'pagos'),
            $this->line($contactsToMove, 'contacto', 'contactos'),
            $this->line($billing['move'], 'dirección de facturación', 'direcciones de facturación'),
            $this->line($billing['collapse'], 'dirección repetida (mismo ID fiscal)', 'direcciones repetidas (mismo ID fiscal)'),
            $this->line($this->countOwned(TeamPassword::class, $source->id), 'contraseña', 'contraseñas'),
            $this->line(
                FiscalCustomerMapping::query()->where('enterprise_id', $source->id)->count(),
                'mapeo fiscal',
                'mapeos fiscales',
            ),
            $this->line($this->commissionCount($source->id), 'comisión de afiliado', 'comisiones de afiliados'),
        ]));

        if ($lines === [])
        {
            $lines[] = 'No hay proyectos, facturas ni contactos para mover.';
        }

        if ($survivor->getStripeCustomerId() !== null)
        {
            $lines[] = 'Las suscripciones de Stripe siguen en el código que se conserva.';
        }

        return $lines;
    }

    private function moveRecords(Enterprise $source, Enterprise $survivor): void
    {
        foreach ([Project::class, Service::class, Invoice::class, Payment::class] as $model)
        {
            $this->owned($model)
                ->where('enterprise_id', $source->id)
                ->update(['enterprise_id' => $survivor->id]);
        }

        $this->moveBillingAddresses($source, $survivor);
        $this->moveContacts($source, $survivor);
        $this->moveFiscalMappings($source, $survivor);

        TeamPassword::withoutGlobalScopes()
            ->withTrashed()
            ->where('enterprise_id', $source->id)
            ->update(['enterprise_id' => $survivor->id]);

        BillingAffiliateCommission::query()
            ->where('paying_enterprise_id', $source->id)
            ->update(['paying_enterprise_id' => $survivor->id]);

        BillingAffiliateCommission::query()
            ->where('referrer_enterprise_id', $source->id)
            ->update(['referrer_enterprise_id' => $survivor->id]);
    }

    private function moveBillingAddresses(Enterprise $source, Enterprise $survivor): void
    {
        $keptByIdentification = [];

        foreach ($this->billingAddresses($survivor->id) as $address)
        {
            $key = $this->identificationKey($address->identification_number);
            if ($key !== '' && ! isset($keptByIdentification[$key]))
            {
                $keptByIdentification[$key] = $address;
            }
        }

        foreach ($this->billingAddresses($source->id) as $address)
        {
            $key = $this->identificationKey($address->identification_number);
            $kept = $key !== '' ? ($keptByIdentification[$key] ?? null) : null;

            if ($kept instanceof EnterpriseBillingAddress && $kept->deleted_at !== null && $address->deleted_at === null)
            {
                Invoice::withoutGlobalScopes()
                    ->withTrashed()
                    ->where('billing_id', $kept->id)
                    ->update(['billing_id' => $address->id]);
                $address->enterprise_id = $survivor->id;
                $address->save();
                $keptByIdentification[$key] = $address;

                continue;
            }

            if ($kept instanceof EnterpriseBillingAddress)
            {
                Invoice::withoutGlobalScopes()
                    ->withTrashed()
                    ->where('billing_id', $address->id)
                    ->update(['billing_id' => $kept->id]);
                $address->delete();

                continue;
            }

            $address->enterprise_id = $survivor->id;
            $address->save();
            if ($key !== '')
            {
                $keptByIdentification[$key] = $address;
            }
        }
    }

    private function moveContacts(Enterprise $source, Enterprise $survivor): void
    {
        $survivorContactIds = DB::table('contact_enterprise')
            ->where('enterprise_id', $survivor->id)
            ->pluck('contact_id');

        DB::table('contact_enterprise')
            ->where('enterprise_id', $source->id)
            ->whereIn('contact_id', $survivorContactIds)
            ->delete();

        DB::table('contact_enterprise')
            ->where('enterprise_id', $source->id)
            ->update([
                'enterprise_id' => $survivor->id,
                'updated_at' => now(),
            ]);

        $rows = DB::table('contact_enterprise')
            ->where('enterprise_id', $survivor->id)
            ->orderBy('id')
            ->get(['id', 'contact_id']);
        $seen = [];
        $duplicateIds = [];
        foreach ($rows as $row)
        {
            if (isset($seen[$row->contact_id]))
            {
                $duplicateIds[] = $row->id;

                continue;
            }

            $seen[$row->contact_id] = true;
        }

        if ($duplicateIds !== [])
        {
            DB::table('contact_enterprise')->whereIn('id', $duplicateIds)->delete();
        }

        Contact::withoutGlobalScopes()
            ->withTrashed()
            ->where('current_enterprise_id', $source->id)
            ->update(['current_enterprise_id' => $survivor->id]);
    }

    private function moveFiscalMappings(Enterprise $source, Enterprise $survivor): void
    {
        $existingPlatforms = FiscalCustomerMapping::query()
            ->where('enterprise_id', $survivor->id)
            ->pluck('platform')
            ->all();

        $mappings = FiscalCustomerMapping::query()
            ->where('enterprise_id', $source->id)
            ->get();

        foreach ($mappings as $mapping)
        {
            if (in_array($mapping->platform, $existingPlatforms, true))
            {
                $mapping->delete();

                continue;
            }

            $mapping->enterprise_id = $survivor->id;
            $mapping->save();
            $existingPlatforms[] = $mapping->platform;
        }
    }

    private function fillEmptyFields(Enterprise $source, Enterprise $survivor): void
    {
        foreach ([
            'phone',
            'whatsapp',
            'email',
            'website',
            'referred_by',
            'address',
            'postal_code',
            'locality',
            'province',
            'country',
            'responsible_id',
            'payment_type_id',
            'invoice_type_id',
        ] as $field)
        {
            if ($this->isBlank($survivor->{$field}) && ! $this->isBlank($source->{$field}))
            {
                $survivor->{$field} = $source->{$field};
            }
        }

        if ($this->isBlank($survivor->code) && $source->getStripeCustomerId() === null && ! $this->isBlank($source->code))
        {
            $survivor->code = $source->code;
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

    private function archive(Enterprise $source, Enterprise $survivor): void
    {
        $data = (array) ($source->data ?? []);
        $data['merged_into_enterprise_id'] = $survivor->id;
        $data['merged_at'] = now()->toIso8601String();
        $source->data = (object) $data;

        if ($source->getStripeCustomerId() !== null && $source->getStripeCustomerId() === $survivor->getStripeCustomerId())
        {
            $source->code = null;
        }

        $source->save();
        $source->delete();
    }

    /**
     * @return array{move: int, collapse: int}
     */
    private function billingPlan(Enterprise $source, Enterprise $survivor): array
    {
        $kept = [];
        foreach ($this->billingAddresses($survivor->id) as $address)
        {
            $key = $this->identificationKey($address->identification_number);
            if ($key !== '')
            {
                $kept[$key] = true;
            }
        }

        $move = 0;
        $collapse = 0;
        foreach ($this->billingAddresses($source->id) as $address)
        {
            $key = $this->identificationKey($address->identification_number);
            if ($key !== '' && isset($kept[$key]))
            {
                $collapse++;

                continue;
            }

            $move++;
            if ($key !== '')
            {
                $kept[$key] = true;
            }
        }

        return ['move' => $move, 'collapse' => $collapse];
    }

    /**
     * @return \Illuminate\Support\Collection<int, EnterpriseBillingAddress>
     */
    private function billingAddresses(int $enterpriseId)
    {
        return EnterpriseBillingAddress::withTrashed()
            ->where('enterprise_id', $enterpriseId)
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function owned(string $model): Builder
    {
        $query = $model::withoutGlobalScopes();
        if (in_array(SoftDeletes::class, class_uses_recursive($model), true))
        {
            $query->withTrashed();
        }

        return $query;
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function countOwned(string $model, int $enterpriseId): int
    {
        return $this->owned($model)
            ->where('enterprise_id', $enterpriseId)
            ->count();
    }

    private function commissionCount(int $enterpriseId): int
    {
        return BillingAffiliateCommission::query()
            ->where(function ($query) use ($enterpriseId): void
            {
                $query->where('paying_enterprise_id', $enterpriseId)
                    ->orWhere('referrer_enterprise_id', $enterpriseId);
            })
            ->count();
    }

    private function identificationKey(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function isBlank(mixed $value): bool
    {
        if ($value === null)
        {
            return true;
        }

        return is_string($value) && trim($value) === '';
    }

    private function line(int $count, string $singular, string $plural): ?string
    {
        if ($count < 1)
        {
            return null;
        }

        return $count.' '.($count === 1 ? $singular : $plural);
    }
}
