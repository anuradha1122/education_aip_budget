<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseCategoryAuditController extends Controller
{
    public function index(): Response
    {
        $events = DB::table('expense_category_audit_events as events')
            ->join('expense_categories', 'expense_categories.id', '=', 'events.expense_category_id')
            ->leftJoin('users', 'users.id', '=', 'events.actor_id')
            ->orderByDesc('events.id')
            ->paginate(20, [
                'events.id',
                'events.action',
                'events.old_values',
                'events.new_values',
                'events.created_at',
                'expense_categories.code as category_code',
                'expense_categories.name as category_name',
                'users.name as actor_name',
            ]);

        return Inertia::render('expense-categories/audit', [
            'events' => $events,
        ]);
    }
}
