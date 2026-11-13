<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AipActivityOfficeService
{
    /**
     * Link an office to a common AIP activity.
     */
    public function create(array $input): int
    {
        $validated = Validator::make($input, [
            'aip_activity_id' => ['required', 'integer', 'min:1'],
            'office_id' => ['required', 'integer', 'min:1'],
        ])->validate();

        return DB::transaction(function () use ($validated) {
            $activity = DB::table('aip_activities')
                ->where('id', $validated['aip_activity_id'])
                ->first();

            if ($activity === null) {
                throw ValidationException::withMessages([
                    'aip_activity_id' => 'The selected activity does not exist.',
                ]);
            }

            // Lock the year first, matching AIP activity creation.
            $year = DB::table('financial_years')
                ->where('id', $activity->financial_year_id)
                ->lockForUpdate()
                ->first();

            if ($year === null) {
                throw ValidationException::withMessages([
                    'aip_activity_id' => 'The activity financial year does not exist.',
                ]);
            }

            if ((bool) $year->is_closed) {
                throw ValidationException::withMessages([
                    'aip_activity_id' => 'Offices cannot be linked to activities in a closed financial year.',
                ]);
            }

            $office = DB::table('offices')
                ->where('id', $validated['office_id'])
                ->lockForUpdate()
                ->first();

            if ($office === null) {
                throw ValidationException::withMessages([
                    'office_id' => 'The selected office does not exist.',
                ]);
            }

            $alreadyLinked = DB::table('aip_activity_offices')
                ->where('aip_activity_id', $activity->id)
                ->where('office_id', $office->id)
                ->exists();

            if ($alreadyLinked) {
                throw ValidationException::withMessages([
                    'office_id' => 'This office is already linked to the activity.',
                ]);
            }

            return DB::table('aip_activity_offices')->insertGetId([
                'aip_activity_id' => $activity->id,
                'office_id' => $office->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}