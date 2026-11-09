<?php

namespace App\Http\Controllers;

use App\Models\FinancialYear;
use App\Services\FinancialYearService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinancialYearController extends Controller
{
    public function index(): Response
    {
        $financialYears = FinancialYear::query()
            ->orderByDesc('year')
            ->get()
            ->map(fn (FinancialYear $financialYear) => [
                'id' => $financialYear->id,
                'year' => $financialYear->year,
                'start_date' => $financialYear->start_date->toDateString(),
                'end_date' => $financialYear->end_date->toDateString(),
                'is_closed' => $financialYear->is_closed,
            ]);

        return Inertia::render('financial-years/index', [
            'financialYears' => $financialYears,
        ]);
    }

    public function store(
        Request $request,
        FinancialYearService $service
    ): RedirectResponse {
        $service->create(
            $request->only(['year', 'start_date', 'end_date']),
            $request->user()
        );

        return redirect()->route('financial-years.index');
    }
}