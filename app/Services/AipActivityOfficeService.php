<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class AipActivityOfficeService
{
    /**
     * Link an office to a common AIP activity.
     */
    public function create(array $input, ?User $actor = null): int
    {
        $validated = Validator::make($input, [
            'aip_activity_id' => ['required', 'integer', 'min:1'],
            'office_id' => ['required', 'integer', 'min:1'],
        ])->validate();

        return DB::transaction(function () use ($validated, $actor) {
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

            $linkId = DB::table('aip_activity_offices')->insertGetId([
                'aip_activity_id' => $activity->id,
                'office_id' => $office->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('aip_activity_office_audit_events')->insert([
                'aip_activity_office_id' => $linkId,
                'actor_id' => $actor?->id,
                'action' => 'created',
                'new_values' => json_encode([
                    'aip_activity_id' => $activity->id,
                    'financial_year_id' => $year->id,
                    'office_id' => $office->id,
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $linkId;
        });
    }
}