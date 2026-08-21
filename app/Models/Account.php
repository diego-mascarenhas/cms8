<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Account extends Model
{
    protected $table = 'teams';

    protected $fillable = [
        'name',
        'user_id',
        'personal_team',
        'stripe_id',
    ];

    protected $appends = ['active_clients_count'];

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'team_id');
    }

    public function getActiveClientsCountAttribute()
    {
        return $this->contacts()
            ->withoutGlobalScope('team')
            ->where('status_id', 5)
            ->count();
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function billingEnterprise(): HasOne
    {
        return $this->hasOne(Enterprise::class, 'code', 'stripe_id')->withoutGlobalScopes();
    }

    public function responsiblePersonName(): string
    {
        $contact = $this->billingEnterprise?->quoteContact();
        $contactName = trim(implode(' ', array_filter([
            $contact?->name,
            $contact?->surname,
        ], static fn ($part): bool => filled($part))));

        if ($contactName !== '')
        {
            return $contactName;
        }

        $ownerName = trim((string) ($this->owner?->name ?? ''));
        if ($ownerName !== '')
        {
            return $ownerName;
        }

        return (string) $this->name;
    }

    public function scopeWhereResponsibleMatches(Builder $query, string $keyword): Builder
    {
        $keyword = trim($keyword);
        if ($keyword === '')
        {
            return $query;
        }

        $like = '%'.$keyword.'%';
        $fullNameSql = self::contactFullNameSql($query);

        return $query->where(function (Builder $inner) use ($like, $fullNameSql): void
        {
            $inner->where('teams.name', 'like', $like)
                ->orWhereHas('owner', function (Builder $ownerQuery) use ($like): void
                {
                    $ownerQuery->where('users.name', 'like', $like)
                        ->orWhere('users.email', 'like', $like)
                        ->orWhereRaw('CAST(users.phone AS CHAR) LIKE ?', [$like]);
                })
                ->orWhereExists(function ($sub) use ($like, $fullNameSql): void
                {
                    $sub->selectRaw('1')
                        ->from('enterprises')
                        ->join('contact_enterprise', 'contact_enterprise.enterprise_id', '=', 'enterprises.id')
                        ->join('contacts', 'contacts.id', '=', 'contact_enterprise.contact_id')
                        ->whereColumn('enterprises.code', 'teams.stripe_id')
                        ->where('teams.stripe_id', 'like', 'cus_%')
                        ->where('enterprises.code', 'like', 'cus_%')
                        ->whereNull('enterprises.deleted_at')
                        ->whereNull('contacts.deleted_at')
                        ->whereRaw('contacts.id = ('.self::quoteContactIdSubquery($fullNameSql).')')
                        ->where(function ($names) use ($like, $fullNameSql): void
                        {
                            $names->where('contacts.name', 'like', $like)
                                ->orWhere('contacts.surname', 'like', $like)
                                ->orWhereRaw($fullNameSql.' like ?', [$like])
                                ->orWhere('contacts.email', 'like', $like)
                                ->orWhereRaw('CAST(contacts.phone AS CHAR) LIKE ?', [$like]);
                        });
                });
        });
    }

    private static function contactFullNameSql(Builder $query): string
    {
        return in_array($query->getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)
            ? "trim(coalesce(contacts.name, '') || ' ' || coalesce(contacts.surname, ''))"
            : "trim(concat(coalesce(contacts.name, ''), ' ', coalesce(contacts.surname, '')))";
    }

    private static function quoteContactIdSubquery(string $fullNameSql): string
    {
        $orderSql = str_replace('contacts.', 'c2.', $fullNameSql);

        return 'select c2.id
            from contact_enterprise as ce2
            inner join contacts as c2 on c2.id = ce2.contact_id
            where ce2.enterprise_id = enterprises.id
              and c2.deleted_at is null
              and c2.email is not null
              and trim(c2.email) <> \'\'
            order by lower('.$orderSql.')
            limit 1';
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'team_user', 'team_id', 'user_id');
    }

    public function getMembersCountAttribute()
    {
        return $this->users()->count();
    }

    public function getTotalTimeAttribute()
    {
        return $this->users()
            ->join('user_contact_actions', 'users.id', '=', 'user_contact_actions.user_id')
            ->where('duration_seconds', '>', 0)
            ->sum('duration_seconds');
    }

    public function subscriptions()
    {
        return $this->hasMany(\Laravel\Cashier\Subscription::class, 'team_id')->orderByDesc('created_at');
    }

    public function getSubscriptionsCountAttribute()
    {
        return $this->subscriptions()
            ->where('stripe_status', '!=', 'canceled')
            ->count();
    }

    public function formatSeconds($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        if ($hours > 0)
        {
            return sprintf('%dh %dm', $hours, $minutes);
        }

        return sprintf('%dm', $minutes);
    }
}
