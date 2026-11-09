<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SubjectAuditController extends Controller
{
    public function index(): Response
    {
        $events = DB::table('subject_audit_events')
            ->join(
                'subjects',
                'subject_audit_events.subject_id',
                '=',
                'subjects.id'
            )
            ->leftJoin(
                'users',
                'subject_audit_events.actor_id',
                '=',
                'users.id'
            )
            ->select([
                'subject_audit_events.id',
                'subject_audit_events.action',
                'subject_audit_events.old_values',
                'subject_audit_events.new_values',
                'subject_audit_events.created_at',
                'subjects.code as subject_code',
                'subjects.name as subject_name',
                'users.name as actor_name',
            ])
            ->orderByDesc('subject_audit_events.created_at')
            ->orderByDesc('subject_audit_events.id')
            ->paginate(15)
            ->through(function ($event) {
                return [
                    'id' => $event->id,
                    'subject_code' => $event->subject_code,
                    'subject_name' => $event->subject_name,
                    'action' => $event->action,
                    'actor_name' => $event->actor_name,
                    'created_at' => $event->created_at,
                    'old_values' => $event->old_values === null
                        ? null
                        : json_decode($event->old_values, true),
                    'new_values' => $event->new_values === null
                        ? null
                        : json_decode($event->new_values, true),
                ];
            });

        return Inertia::render('subjects/audit', [
            'events' => $events,
        ]);
    }
}