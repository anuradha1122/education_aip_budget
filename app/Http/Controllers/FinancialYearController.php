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
        return $this->renderPage();
    }

    public function edit(FinancialYear $financialYear): Response
    {
        return $this->renderPage($financialYear);
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

    public function update(
        Request $request,
        FinancialYear $financialYear,
        FinancialYearService $service
    ): RedirectResponse {
        $service->update(
            $financialYear,
            $request->only(['year', 'start_date', 'end_date']),
            $request->user()
        );

        return redirect()->route('financial-years.index');
    }

    private function renderPage(?FinancialYear $editingYear = null): Response
    {
        $financialYears = FinancialYear::query()
            ->orderByDesc('year')
            ->get()
            ->map(fn (FinancialYear $year) => $this->formatYear($year));

        return Inertia::render('financial-years/index', [
            'financialYears' => $financialYears,
            'editingYear' => $editingYear
                ? $this->formatYear($editingYear)
                : null,
        ]);
    }

    private function formatYear(FinancialYear $year): array
    {
        return [
            'id' => $year->id,
            'year' => $year->year,
            'start_date' => $year->start_date->toDateString(),
            'end_date' => $year->end_date->toDateString(),
            'is_closed' => $year->is_closed,
        ];
    }
}