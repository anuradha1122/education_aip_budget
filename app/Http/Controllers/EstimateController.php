<?php

namespace App\Http\Controllers;

use App\Services\EstimateCalculationService;
use App\Services\EstimateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use OverflowException;

class EstimateController extends Controller
{
    public function create(): Response
    {
        $paragraph = DB::table('estimate_circular_settings')->where('id', 1)->value('rule_paragraph');

        $assignments = DB::table('aip_activity_offices as ao')
            ->join('aip_activities as a', 'a.id', '=', 'ao.aip_activity_id')
            ->join('financial_years as y', 'y.id', '=', 'a.financial_year_id')
            ->join('offices as o', 'o.id', '=', 'ao.office_id')
            ->where('y.is_closed', false)->where('o.is_active', true)
            ->orderByDesc('y.year')->orderBy('a.activity_number')->orderBy('o.name')
            ->get(['ao.id', 'y.year', 'a.activity_number', 'a.description', 'o.code as office_code', 'o.name as office_name']);

        return Inertia::render('estimates/create', [
            'assignments' => $assignments,
            'defaultCircularParagraph' => $paragraph ?? '',
        ]);
    }

    public function store(Request $request, EstimateService $service): RedirectResponse
    {
        try {
            $estimate = $service->create($request->all());
        } catch (OverflowException $exception) {
            throw ValidationException::withMessages([
                'items' => 'The total is too large to calculate. Please check the amounts.',
            ]);
        }

        return redirect()->route('estimates.show', $estimate['id']);
    }

    public function show(int $id, EstimateCalculationService $calculator): Response
    {
        $estimate = DB::table('estimates')->where('id', $id)->first();
        abort_if($estimate === null, 404);
        $items = DB::table('estimate_items')->where('estimate_id', $id)
            ->orderBy('sort_order')->orderBy('id')->get();
        $amounts = $items->map(fn ($item) => $calculator->lineAmount(
            (int) $item->rate_in_cents, (int) $item->units, (int) $item->people
        ));

        return Inertia::render('estimates/show', [
            'estimate' => $estimate,
            'items' => $items,
            'totalInCents' => (string) $calculator->total($amounts->all()),
        ]);
    }
}
