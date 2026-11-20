<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AipRegisterScope
{
    /** Non-admin scope. A subject director must have a matching active officer. */
    public function forUser(User $user): ?object
    {
        $provincial = DB::table('user_access_assignments')
            ->where('user_id', $user->id)->where('role', 'provincial_admin')
            ->whereNull('office_id')->first(['role', 'office_id']);
        if ($provincial !== null) {
            return $provincial;
        }

        return DB::table('user_access_assignments as a')
            ->join('offices as office', 'office.id', '=', 'a.office_id')
            ->leftJoin('user_officer_links as link', 'link.user_id', '=', 'a.user_id')
            ->leftJoin('officers as officer', 'officer.id', '=', 'link.officer_id')
            ->where('a.user_id', $user->id)->where('office.is_active', true)
            ->where(function ($query) {
                $query->where('a.role', 'zonal_admin')->orWhere(function ($query) {
                    $query->where('a.role', 'subject_director')
                        ->where('officer.is_active', true)
                        ->whereColumn('officer.office_id', 'a.office_id');
                });
            })
            ->select('a.role', 'a.office_id', 'officer.id as officer_id')->first();
    }
}
