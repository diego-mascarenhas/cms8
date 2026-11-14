<?php

namespace App\Services\Hosting;

use App\Models\Category;
use App\Models\Domain;
use App\Models\Module;
use App\Models\Service;
use App\Models\User;

class HostingClientLinker
{
    /**
     * Link a hosting account to a client enterprise (or clear the link).
     */
    public function link(User $user, Domain $domain, ?int $enterpriseId): Domain
    {
        $domain->loadMissing('server', 'service');

        if ($enterpriseId === null)
        {
            $domain->service_id = null;
            $domain->save();

            return $domain->fresh(['server', 'service.enterprise.contacts', 'service.serviceSync']) ?? $domain;
        }

        $service = $domain->service;

        if ($service instanceof Service)
        {
            $service->enterprise_id = $enterpriseId;
            $service->save();
        } else
        {
            $domain->service_id = $this->createService($user, $domain, $enterpriseId);
            $domain->save();
        }

        return $domain->fresh(['server', 'service.enterprise.contacts', 'service.serviceSync']) ?? $domain;
    }

    private function createService(User $user, Domain $domain, int $enterpriseId): int
    {
        $teamId = $user->currentTeam?->id;
        $categoryId = Category::query()
            ->when($teamId, fn ($query) => $query->where('team_id', $teamId))
            ->where('name', 'like', '%Hosting%')
            ->value('id');

        if ($categoryId === null)
        {
            $moduleId = Module::query()->where('key', 'services')->value('id');
            $categoryId = Category::query()->create([
                'name' => 'Hosting',
                'team_id' => $teamId,
                'module_id' => $moduleId,
                'status' => true,
            ])->id;
        }

        $server = $domain->server;

        $service = Service::withoutGlobalScopes()->create([
            'enterprise_id' => $enterpriseId,
            'category_id' => $categoryId,
            'operation' => 'sell',
            'description' => 'Hosting '.$domain->domain,
            'data' => [
                'domain' => $domain->domain,
                'username' => $domain->username,
                'plan' => $domain->plan,
                'server_id' => $server?->id,
                'server_url' => $server?->server_url,
            ],
            'responsible_id' => $user->id,
            'status' => 4,
        ]);

        return (int) $service->id;
    }
}
