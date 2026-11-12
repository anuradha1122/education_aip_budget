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

        return Inertia::render('estimates/create', [
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
