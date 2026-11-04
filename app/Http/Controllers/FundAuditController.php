<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FundAuditController extends Controller
{
    public function index(): Response
    {
        $events = DB::table('fund_audit_events as events')
            ->join('funds', 'funds.id', '=', 'events.fund_id')
            ->leftJoin('users', 'users.id', '=', 'events.actor_id')
            ->orderByDesc('events.id')
            ->paginate(20, [
                'events.id',
                'events.action',
                'events.old_values',
                'events.new_values',
                'events.created_at',
                'funds.code as fund_code',
                'funds.name as fund_name',
                'users.name as actor_name',
            ]);

        return Inertia::render('funds/audit', [
            'events' => $events,
        ]);
    }
}
