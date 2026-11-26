<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserManagementScope
{
    /** Return a valid management scope from current account and assignment data. */
    public function forUser(User $actor): ?object
    {
        $user = DB::table('users')->where('id', $actor->id)->first(['id', 'is_admin', 'is_active']);
        if (! $user || ! (bool) $user->is_active) return null;
        if ((bool) $user->is_admin) return (object) ['role' => 'system_admin', 'office_id' => null];
        $assignment = DB::table('user_access_assignments')->where('user_id', $user->id)->first(['role', 'office_id']);
        if (! $assignment) return null;
        if ($assignment->role === 'provincial_admin' && $assignment->office_id === null) return $assignment;
        if ($assignment->role === 'zonal_admin' && $assignment->office_id !== null && DB::table('offices')->where('id', $assignment->office_id)->where('is_active', true)->exists()) return $assignment;
        return null;
    }

    /** Caller locks the target user before mutation; assignments use the same lock. */
    public function canManageTarget(User $actor, object $target): bool
    {
        $scope = $this->forUser($actor);
        if (! $scope || (bool) $target->is_admin) return false;
        if ($scope->role !== 'zonal_admin') return true;
        return DB::table('user_access_assignments')->where('user_id', $target->id)->where('role', 'subject_director')->where('office_id', $scope->office_id)->exists();
    }

    public function constrainUsers(\Illuminate\Database\Query\Builder $query, User $actor): \Illuminate\Database\Query\Builder
    {
        $scope = $this->forUser($actor);
        abort_if($scope === null, 403);
        if ($scope->role === 'zonal_admin') {
            $query->where('u.is_admin', false)->where('a.role', 'subject_director')->where('a.office_id', $scope->office_id);
        }
        return $query;
    }

    public function canViewTarget(User $actor, object $target): bool
    {
        $scope = $this->forUser($actor);
        return $scope !== null && ($scope->role !== 'zonal_admin' || $this->canManageTarget($actor, $target));
    }

    public function canAssign(User $actor, string $role, ?int $officeId): bool
    {
        $scope = $this->forUser($actor);
        if (! $scope) return false;
        return $scope->role !== 'zonal_admin' || ($role === 'subject_director' && $officeId === (int) $scope->office_id);
    }
}
