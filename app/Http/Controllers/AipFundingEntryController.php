<?php

namespace App\Http\Controllers;

use App\Services\AipActivityFundingEntryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AipFundingEntryController extends Controller
{
    public function index(): Response
    {
        $activities = DB::table('aip_activities as a')
            ->join('financial_years as y', 'y.id', '=', 'a.financial_year_id')
            ->where('y.is_closed', false)
            ->orderByDesc('y.year')
            ->orderBy('a.activity_number')
            ->get([
                'a.id',
                'a.activity_number',
                'a.description',
                'y.year',
            ]);

        $votes = DB::table('votes')
            ->orderBy('code')
            ->get(['id', 'code', 'description', 'is_active']);

        $funds = DB::table('funds')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'is_active']);

        $entries = DB::table('aip_activity_funding_entries as e')
            ->join('aip_activities as a', 'a.id', '=', 'e.aip_activity_id')
            ->join('financial_years as y', 'y.id', '=', 'a.financial_year_id')
            ->join('votes as v', 'v.id', '=', 'e.vote_id')
            ->join('funds as f', 'f.id', '=', 'e.fund_id')
            ->orderByDesc('e.id')
            ->paginate(20, [
                'e.id',
                'y.year',
                'a.activity_number',
                'a.description as activity_description',
                'v.code as vote_code',
                'f.code as fund_code',
                'f.name as fund_name',
            ]);

        return Inertia::render('aip-funding-entries/index', [
            'activities' => $activities,
            'votes' => $votes,
            'funds' => $funds,
            'entries' => $entries,
        ]);
    }

    public function store(
        Request $request,
        AipActivityFundingEntryService $service
    ): RedirectResponse {
        $service->create(
            $request->only(['aip_activity_id', 'vote_id', 'fund_id']),
            $request->user()
        );

        return redirect()->route('aip-funding-entries.index');
    }
}