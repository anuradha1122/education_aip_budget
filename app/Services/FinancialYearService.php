<?php

namespace App\Services;

use App\Models\FinancialYear;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FinancialYearService
{
    public function create(array $data, ?User $actor = null): FinancialYear
    {
        $validated = Validator::make($data, [
            'year' => [
                'required',
                'integer',
                'min:1',
                'max:65535',
                Rule::unique('financial_years', 'year'),
            ],
            'start_date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'end_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
            ],
        ])->validate();

        $validated['is_closed'] = false;

        return DB::transaction(function () use ($validated, $actor) {
            $financialYear = FinancialYear::create($validated);

            DB::table('financial_year_audit_events')->insert([
                'financial_year_id' => $financialYear->id,
                'actor_id' => $actor?->id,
                'action' => 'created',
                'old_values' => null,
                'new_values' => json_encode([
                    'year' => $financialYear->year,
                    'start_date' => $financialYear->start_date->toDateString(),
                    'end_date' => $financialYear->end_date->toDateString(),
                    'is_closed' => $financialYear->is_closed,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $financialYear;
        });
    }
}