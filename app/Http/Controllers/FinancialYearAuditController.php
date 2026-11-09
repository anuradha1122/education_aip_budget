<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FinancialYearAuditController extends Controller
{
    public function index(): Response
    {
        $events = DB::table('financial_year_audit_events')
            ->join(
                'financial_years',
                'financial_year_audit_events.financial_year_id',
                '=',
                'financial_years.id'
            )
            ->leftJoin(
                'users',
                'financial_year_audit_events.actor_id',
                '=',
                'users.id'
            )
            ->select([
                'financial_year_audit_events.id',
                'financial_year_audit_events.action',
                'financial_year_audit_events.old_values',
                'financial_year_audit_events.new_values',
                'financial_year_audit_events.created_at',
                'financial_years.year',
                'users.name as actor_name',
            ])
            ->orderByDesc('financial_year_audit_events.created_at')
            ->orderByDesc('financial_year_audit_events.id')
            ->paginate(15)
            ->through(function ($event) {
                return [
                    'id' => $event->id,
                    'year' => $event->year,
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

        return Inertia::render('financial-years/audit', [
            'events' => $events,
        ]);
    }
}