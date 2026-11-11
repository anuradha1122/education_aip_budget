<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DesignationAuditController extends Controller
{
    public function index(): Response
    {
        $events = DB::table('designation_audit_events')
            ->join(
                'designations',
                'designation_audit_events.designation_id',
                '=',
                'designations.id'
            )
            ->leftJoin(
                'users',
                'designation_audit_events.actor_id',
                '=',
                'users.id'
            )
            ->orderByDesc('designation_audit_events.id')
            ->get([
                'designation_audit_events.id',
                'designation_audit_events.action',
                'designation_audit_events.old_values',
                'designation_audit_events.new_values',
                'designation_audit_events.created_at',
                'designations.code as designation_code',
                'designations.name as designation_name',
                'users.name as actor_name',
            ])
            ->map(function ($event) {
                $event->old_values = $event->old_values === null
                    ? null
                    : json_decode($event->old_values, true);

                $event->new_values = json_decode($event->new_values, true);

                return $event;
            });

        return Inertia::render('designations/audit', [
            'events' => $events,
        ]);
    }
}