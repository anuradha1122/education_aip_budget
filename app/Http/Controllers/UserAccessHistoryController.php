<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserAccessHistoryController extends Controller
{
    public function index(User $user): Response
    {
        abort_unless(app(\App\Services\UserManagementScope::class)->canViewTarget(request()->user(), $user), 403);
        $allowed = ['name', 'email', 'role', 'office_id', 'is_active', 'password_changed', 'officer_id', 'can_import_aip'];
        $events = DB::table('user_access_assignment_audit_events as event')
            ->leftJoin('users as actor', 'actor.id', '=', 'event.actor_id')
            ->where('event.user_id', $user->id)
            ->orderByDesc('event.id')
            ->paginate(20, ['event.id', 'event.action', 'event.old_values', 'event.new_values', 'event.created_at', 'actor.name as actor_name'])
            ->through(function ($event) use ($allowed) {
                foreach (['old_values', 'new_values'] as $field) {
                    $values = json_decode($event->{$field} ?? '', true);
                    $event->{$field} = is_array($values) ? Arr::only($values, $allowed) : null;
                }
                return $event;
            });

        // Resolve only IDs referenced by this page. Audit values remain unchanged.
        $officeIds = [];
        foreach ($events->items() as $event) {
            foreach (['old_values', 'new_values'] as $field) {
                $id = $event->{$field}['office_id'] ?? null;
                if ((is_int($id) || (is_string($id) && ctype_digit($id))) && (int) $id > 0) {
                    $officeIds[] = (int) $id;
                }
            }
        }
        $offices = DB::table('offices')->whereIn('id', array_unique($officeIds))
            ->get(['id', 'code', 'name'])->keyBy('id');

        return Inertia::render('user-management/history', [
            'subjectUser' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'events' => $events,
            'offices' => $offices,
        ]);
    }
}
