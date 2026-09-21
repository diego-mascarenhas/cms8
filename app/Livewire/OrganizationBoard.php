<?php

namespace App\Livewire;

use App\Models\EnterpriseDepartment;
use App\Models\EnterpriseOrganization;
use App\Support\RevisionAlphaOrganization;
use Livewire\Component;

class OrganizationBoard extends Component
{
    /**
     * @return array<int, array{id: int|string, name: string, postits: array<int, array>}>
     */
    public function getDepartmentPostits(): array
    {
        $teamId = auth()->user()->currentTeam->id;

        if (RevisionAlphaOrganization::appliesTo($teamId))
        {
            return $this->catalogDepartmentPostits($teamId);
        }

        return $this->databaseDepartmentPostits($teamId);
    }

    /**
     * @return array<int, array{id: int|string, name: string, postits: array<int, array>}>
     */
    private function catalogDepartmentPostits(int $teamId): array
    {
        $catalog = RevisionAlphaOrganization::viewData(auth()->user());
        $result = [];

        foreach ($catalog['departments'] as $departmentKey => $department)
        {
            $postits = [];

            foreach ($catalog['processes_by_department'][$departmentKey] ?? [] as $process)
            {
                $postits[] = [
                    'id' => 'catalog-'.$process['key'],
                    'header' => $process['name'],
                    'author' => $process['responsible']['name'] ?? 'N/A',
                    'content' => implode("\n", $process['steps']),
                    'steps' => $process['steps'],
                    'time_allocation' => $process['time_allocation'],
                    'color' => $department['color'],
                    'availability' => $process['availability'],
                    'from_catalog' => true,
                    'company' => $process['company']['name'] ?? null,
                ];
            }

            $databaseDepartment = EnterpriseDepartment::query()
                ->where('name', $department['name'])
                ->first();

            if ($databaseDepartment)
            {
                $existingNames = array_map(
                    static fn (array $postit): string => mb_strtolower($postit['header']),
                    $postits,
                );

                foreach ($this->databasePostitsForDepartment($databaseDepartment, $teamId) as $postit)
                {
                    if (! in_array(mb_strtolower($postit['header']), $existingNames, true))
                    {
                        $postits[] = $postit;
                    }
                }
            }

            $result[] = [
                'id' => $databaseDepartment?->id ?? $departmentKey,
                'name' => $department['name'],
                'postits' => $postits,
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{id: int, name: string, postits: array<int, array>}>
     */
    private function databaseDepartmentPostits(int $teamId): array
    {
        $departments = EnterpriseDepartment::orderBy('name')->get();
        $result = [];

        foreach ($departments as $department)
        {
            $result[] = [
                'id' => $department->id,
                'name' => $department->name,
                'postits' => $this->databasePostitsForDepartment($department, $teamId),
            ];
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function databasePostitsForDepartment(EnterpriseDepartment $department, int $teamId): array
    {
        return EnterpriseOrganization::where('department_id', $department->id)
            ->where('team_id', $teamId)
            ->with('responsible')
            ->orderBy('order')
            ->get()
            ->map(function (EnterpriseOrganization $organization) use ($department): array
            {
                return [
                    'id' => $organization->id,
                    'header' => $organization->name,
                    'author' => $organization->responsible?->name ?? 'N/A',
                    'content' => $organization->description,
                    'steps' => [],
                    'time_allocation' => $organization->time_allocation,
                    'color' => $department->color ?? '#feff9c',
                    'availability' => $organization->availability,
                    'from_catalog' => false,
                    'company' => null,
                ];
            })
            ->values()
            ->all();
    }

    public function reorder(int $departmentId, array $orderedIds): void
    {
        $teamId = auth()->user()->currentTeam->id;

        $department = EnterpriseDepartment::find($departmentId);
        if (! $department)
        {
            return;
        }

        $orderedIds = array_map('intval', $orderedIds);

        $validIds = EnterpriseOrganization::where('department_id', $departmentId)
            ->where('team_id', $teamId)
            ->pluck('id')
            ->all();

        $orderedIds = array_values(array_intersect($orderedIds, $validIds));

        foreach ($orderedIds as $position => $id)
        {
            EnterpriseOrganization::where('id', $id)
                ->where('team_id', $teamId)
                ->where('department_id', $departmentId)
                ->update(['order' => $position]);
        }
    }

    /**
     * Move a task to another department and update order in both departments.
     *
     * @param  array  $orderedIdsInTo  All task ids in the target department (new order including the moved task)
     * @param  int|null  $fromDepartmentId  Source department (optional, to reorder remaining items)
     * @param  array  $orderedIdsInFrom  Remaining task ids in source department (new order)
     */
    public function moveToDepartment(int $taskId, int $toDepartmentId, array $orderedIdsInTo, ?int $fromDepartmentId = null, array $orderedIdsInFrom = []): void
    {
        $teamId = auth()->user()->currentTeam->id;

        if (! EnterpriseDepartment::find($toDepartmentId))
        {
            return;
        }

        $orderedIdsInTo = array_map('intval', $orderedIdsInTo);

        foreach ($orderedIdsInTo as $pos => $id)
        {
            EnterpriseOrganization::where('id', $id)
                ->where('team_id', $teamId)
                ->update(['department_id' => $toDepartmentId, 'order' => $pos]);
        }

        if ($fromDepartmentId !== null && $fromDepartmentId !== $toDepartmentId && count($orderedIdsInFrom) > 0)
        {
            $orderedIdsInFrom = array_map('intval', $orderedIdsInFrom);
            foreach ($orderedIdsInFrom as $pos => $id)
            {
                EnterpriseOrganization::where('id', $id)
                    ->where('team_id', $teamId)
                    ->where('department_id', $fromDepartmentId)
                    ->update(['order' => $pos]);
            }
        }
    }

    public function render()
    {
        $teamId = auth()->user()->currentTeam->id;
        $catalog = RevisionAlphaOrganization::appliesTo($teamId)
            ? RevisionAlphaOrganization::viewData(auth()->user())
            : null;

        return view('livewire.organization-board', [
            'departmentPostits' => $this->getDepartmentPostits(),
            'catalog' => $catalog,
        ]);
    }
}
