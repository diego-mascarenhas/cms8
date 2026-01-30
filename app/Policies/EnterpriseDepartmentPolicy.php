<?php

namespace App\Policies;

use App\Models\EnterpriseDepartment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EnterpriseDepartmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, EnterpriseDepartment $enterpriseDepartment): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, EnterpriseDepartment $enterpriseDepartment): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, EnterpriseDepartment $enterpriseDepartment): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, EnterpriseDepartment $enterpriseDepartment): bool
    {
        return false;
    }

    public function forceDelete(User $user, EnterpriseDepartment $enterpriseDepartment): bool
    {
        return false;
    }
}
