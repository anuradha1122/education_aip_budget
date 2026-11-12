<?php

namespace App\Services;

use App\Models\AipActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AipActivityService
{
    public function create(array $input): AipActivity
    {
        foreach (['activity_number', 'description', 'location'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }

        $validated = Validator::make($input, [
            'financial_year_id' => ['required', 'integer'],
            'activity_number' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
        ])->validate();

        return DB::transaction(function () use ($validated) {
            $year = DB::table('financial_years')
                ->where('id', $validated['financial_year_id'])
                ->lockForUpdate()
                ->first();

            if ($year === null) {
                throw ValidationException::withMessages([
                    'financial_year_id' => 'The selected financial year does not exist.',
                ]);
            }

            if ((bool) $year->is_closed) {
                throw ValidationException::withMessages([
                    'financial_year_id' => 'Activities cannot be created for a closed financial year.',
                ]);
            }

            return AipActivity::create([
                'financial_year_id' => $year->id,
                'activity_number' => $validated['activity_number'],
                'description' => $validated['description'],
                'location' => ($validated['location'] ?? '') === '' ? null : $validated['location'],
            ]);
        });
    }
}
