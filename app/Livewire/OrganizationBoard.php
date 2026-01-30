<?php

namespace App\Livewire;

use App\Models\EnterpriseDepartment;
use App\Models\EnterpriseOrganization;
use Livewire\Component;

class OrganizationBoard extends Component
{
    /**
     * @return array<int, array{id: int, name: string, postits: array<int, array>}>
     */
    public function getDepartmentPostits(): array
    {
        $departments = EnterpriseDepartment::orderBy('name')->get();
        $teamId = auth()->user()->currentTeam->id;
        $result = [];

        foreach ($departments as $department)
        {
            $postits = EnterpriseOrganization::where('department_id', $department->id)
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
                        'time_allocation' => $organization->time_allocation,
                        'color' => $department->color ?? '#feff9c',
                        'availability' => $organization->availability,
                    ];
                })
                ->values()
                ->all();

            $result[] = [
                'id' => $department->id,
                'name' => $department->name,
                'postits' => $postits,
            ];
        }

        return $result;
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
        return view('livewire.organization-board', [
            'departmentPostits' => $this->getDepartmentPostits(),
        ]);
    }
}
