<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EstimateService
{
    public function __construct(private EstimateCalculationService $calculator) {}

    /** Create an estimate and return its ID and calculated total in cents. */
    public function create(array $input): array
    {
        $validated = Validator::make($input, [
            'aip_activity_office_id' => ['required', 'integer', 'min:1'],
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
            $assignment = DB::table('aip_activity_offices as ao')
                ->join('aip_activities as a', 'a.id', '=', 'ao.aip_activity_id')
                ->where('ao.id', $validated['aip_activity_office_id'])
                ->first(['ao.id', 'ao.office_id', 'ao.aip_activity_id', 'a.financial_year_id']);
            if ($assignment === null) {
                throw ValidationException::withMessages(['aip_activity_office_id' => 'Select an available activity and office assignment.']);
            }
            // Serialize issuance within a year and follow the existing year-before-office lock order.
            $year = DB::table('financial_years')->where('id', $assignment->financial_year_id)->lockForUpdate()->first();
            $office = DB::table('offices')->where('id', $assignment->office_id)->lockForUpdate()->first();
            $link = DB::table('aip_activity_offices')->where('id', $assignment->id)->lockForUpdate()->first();
            if ($year === null || (bool) $year->is_closed || $office === null || ! (bool) $office->is_active || $link === null || (int) $link->office_id !== (int) $office->id || (int) $link->aip_activity_id !== (int) $assignment->aip_activity_id) {
                throw ValidationException::withMessages(['aip_activity_office_id' => 'Select an active office assignment in an open financial year.']);
            }
            $now = now();
            $sequence = DB::table('estimate_reference_sequences')->where('financial_year_id', $year->id)
                ->where('office_id', $office->id)->lockForUpdate()->first();
            $number = $sequence === null ? 1 : $sequence->last_number + 1;
            if ($sequence === null) {
                DB::table('estimate_reference_sequences')->insert(['financial_year_id' => $year->id, 'office_id' => $office->id,
                    'last_number' => $number, 'created_at' => $now, 'updated_at' => $now]);
            } else {
                DB::table('estimate_reference_sequences')->where('id', $sequence->id)->update(['last_number' => $number, 'updated_at' => $now]);
            }
            $reference = $year->year.'/'.$office->code.'/EST/'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            if (DB::table('estimates')->where('reference', $reference)->exists()) {
                throw ValidationException::withMessages(['aip_activity_office_id' => 'This office code conflicts with an existing estimate reference. Contact an administrator.']);
            }
            $id = DB::table('estimates')->insertGetId([
                'aip_activity_office_id' => $assignment->id,
                'reference' => $reference,
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

            return ['id' => $id, 'reference' => $reference, 'total_in_cents' => $total];
        });
    }
}
