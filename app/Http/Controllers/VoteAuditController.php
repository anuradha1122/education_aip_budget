<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class VoteAuditController extends Controller
{
    public function index(): Response
    {
        $events = DB::table('vote_audit_events')
            ->join('votes', 'vote_audit_events.vote_id', '=', 'votes.id')
            ->leftJoin('users', 'vote_audit_events.actor_id', '=', 'users.id')
            ->select([
                'vote_audit_events.id',
                'vote_audit_events.action',
                'vote_audit_events.old_values',
                'vote_audit_events.new_values',
                'vote_audit_events.created_at',
                'votes.code as vote_code',
                'votes.description as vote_description',
                'users.name as actor_name',
            ])
            ->orderByDesc('vote_audit_events.created_at')
            ->orderByDesc('vote_audit_events.id')
            ->paginate(15)
            ->through(function ($event) {
                return [
                    'id' => $event->id,
                    'action' => $event->action,
                    'old_values' => $event->old_values === null
                        ? null
                        : json_decode($event->old_values, true),
                    'new_values' => $event->new_values === null
                        ? null
                        : json_decode($event->new_values, true),
                    'created_at' => $event->created_at,
                    'vote_code' => $event->vote_code,
                    'vote_description' => $event->vote_description,
                    'actor_name' => $event->actor_name,
                ];
            });

        return Inertia::render('votes/audit', [
            'events' => $events,
        ]);
    }
}