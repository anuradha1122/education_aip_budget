<?php

namespace App\Http\Controllers;

use App\Services\AipActivityOfficeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AipActivityOfficeController extends Controller
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

        $offices = DB::table('offices')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'is_active']);

        $links = DB::table('aip_activity_offices as link')
            ->join('aip_activities as a', 'a.id', '=', 'link.aip_activity_id')
            ->join('financial_years as y', 'y.id', '=', 'a.financial_year_id')
            ->join('offices as o', 'o.id', '=', 'link.office_id')
            ->orderByDesc('link.id')
            ->paginate(20, [
                'link.id',
                'a.activity_number',
                'a.description',
                'y.year',
                'o.code as office_code',
                'o.name as office_name',
            ]);

        return Inertia::render('aip-activity-offices/index', [
            'activities' => $activities,
            'offices' => $offices,
            'links' => $links,
        ]);
    }

    public function store(
        Request $request,
        AipActivityOfficeService $service
    ): RedirectResponse {
        $service->create(
            $request->only(['aip_activity_id', 'office_id']),
            $request->user()
        );

        return redirect()->route('aip-activity-offices.index');
    }
}