<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AipActivityAuditController extends Controller
{
    public function index(): Response
    {
        $events = DB::table('aip_activity_audit_events as events')
            ->join('aip_activities as activities', 'activities.id', '=', 'events.aip_activity_id')
            ->leftJoin('users as actors', 'actors.id', '=', 'events.actor_id')
            ->select('events.id', 'events.action', 'events.old_values', 'events.new_values',
                'events.created_at', 'activities.activity_number as current_activity_number',
                'actors.name as actor_name')
            ->orderByDesc('events.id')->paginate(20);

        return Inertia::render('aip-activities/audit', ['events' => $events]);
    }
}
