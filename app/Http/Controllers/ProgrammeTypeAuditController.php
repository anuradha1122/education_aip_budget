<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProgrammeTypeAuditController extends Controller
{
    public function index(): Response
    {
        $events = DB::table('programme_type_audit_events')
            ->leftJoin(
                'users',
                'programme_type_audit_events.actor_id',
                '=',
                'users.id'
            )
            ->orderByDesc('programme_type_audit_events.id')
            ->get([
                'programme_type_audit_events.id',
                'programme_type_audit_events.programme_type_id',
                'programme_type_audit_events.action',
                'programme_type_audit_events.old_values',
                'programme_type_audit_events.new_values',
                'programme_type_audit_events.created_at',
                'users.name as actor_name',
            ])
            ->map(function ($event) {
                $event->old_values = $event->old_values === null
                    ? null
                    : json_decode($event->old_values, true);

                $event->new_values = json_decode($event->new_values, true);

                return $event;
            });

        return Inertia::render('programme-types/audit', [
            'events' => $events,
        ]);
    }
}