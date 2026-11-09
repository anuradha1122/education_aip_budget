<?php

namespace App\Services;

use App\Models\FinancialYear;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FinancialYearService
{
    public function create(array $data): FinancialYear
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

        return FinancialYear::create($validated);
    }
}