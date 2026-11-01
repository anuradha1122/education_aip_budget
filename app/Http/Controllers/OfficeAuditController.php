<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OfficeAuditController extends Controller
{
    public function index(): Response
    {
        $events = DB::table('office_audit_events as events')
            ->join('offices', 'offices.id', '=', 'events.office_id')
            ->leftJoin('users', 'users.id', '=', 'events.actor_id')
            ->orderByDesc('events.id')
            ->paginate(20, [
                'events.id',
                'events.action',
                'events.old_values',
                'events.new_values',
                'events.created_at',
                'offices.code as office_code',
                'offices.name as office_name',
                'users.name as actor_name',
            ]);

        return Inertia::render('offices/audit', [
            'events' => $events,
        ]);
    }
}
