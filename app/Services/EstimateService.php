<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EstimateService
{
    public function __construct(private EstimateCalculationService $calculator) {}

    /** Create an estimate and return its ID and calculated total in cents. */
    public function create(array $input): array
    {
        $validated = Validator::make($input, [
            'title' => ['required', 'string', 'max:255'],
            'circular_paragraph' => ['nullable', 'string', 'max:10000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.rate_in_cents' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'items.*.units' => ['required', 'integer', 'min:0', 'max:1000000'],
            'items.*.people' => ['required', 'integer', 'min:0', 'max:1000000'],
        ])->validate();

        $lineAmounts = [];
        foreach ($validated['items'] as $item) {
            $lineAmounts[] = $this->calculator->lineAmount(
                (int) $item['rate_in_cents'],
                (int) $item['units'],
                (int) $item['people'],
            );
        }
        $total = $this->calculator->total($lineAmounts);

        return DB::transaction(function () use ($validated, $total) {
            $now = now();
            $id = DB::table('estimates')->insertGetId([
                'title' => $validated['title'],
                'circular_paragraph' => $validated['circular_paragraph'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (array_values($validated['items']) as $index => $item) {
                DB::table('estimate_items')->insert([
                    'estimate_id' => $id,
                    'description' => $item['description'],
                    'rate_in_cents' => (int) $item['rate_in_cents'],
                    'units' => (int) $item['units'],
                    'people' => (int) $item['people'],
                    'sort_order' => $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return ['id' => $id, 'total_in_cents' => $total];
        });
    }
}
