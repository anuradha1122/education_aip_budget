<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EstimateCircularSettingsController extends Controller
{
    public function index(): Response
    {
        $settings = DB::table('estimate_circular_settings')->where('id', 1)->first();

        return Inertia::render('estimate-circular-settings/index', [
            'settings' => [
                'circular_number' => $settings?->circular_number ?? '',
                'rule_paragraph' => $settings?->rule_paragraph ?? '',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'circular_number' => ['nullable', 'string', 'max:255'],
            'rule_paragraph' => ['nullable', 'string', 'max:10000'],
        ]);

        DB::table('estimate_circular_settings')->upsert([
            [
                'id' => 1,
                'circular_number' => $data['circular_number'] ?? null,
                'rule_paragraph' => $data['rule_paragraph'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['id'], ['circular_number', 'rule_paragraph', 'updated_at']);

        return redirect()->route('estimate-circular-settings.index');
    }
}
