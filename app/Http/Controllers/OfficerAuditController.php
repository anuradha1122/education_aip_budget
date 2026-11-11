<?php

namespace App\Http\Controllers;

use App\Models\Designation;
use App\Models\Office;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OfficerAuditController extends Controller
{
    public function index(): Response
    {
        $events = DB::table('officer_audit_events')
            ->join(
                'officers',
                'officer_audit_events.officer_id',
                '=',
                'officers.id'
            )
            ->leftJoin(
                'users',
                'officer_audit_events.actor_id',
                '=',
                'users.id'
            )
            ->orderByDesc('officer_audit_events.id')
            ->get([
                'officer_audit_events.id',
                'officer_audit_events.action',
                'officer_audit_events.old_values',
                'officer_audit_events.new_values',
                'officer_audit_events.created_at',
                'officers.id as officer_id',
                'users.name as actor_name',
            ])
            ->map(function ($event) {
                $event->old_values = $event->old_values === null
                    ? null
                    : json_decode($event->old_values, true);

                $event->new_values = json_decode($event->new_values, true);

                return $event;
            });

        return Inertia::render('officers/audit', [
            'events' => $events,
            'offices' => Office::query()
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'designations' => Designation::query()
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        ]);
    }
}