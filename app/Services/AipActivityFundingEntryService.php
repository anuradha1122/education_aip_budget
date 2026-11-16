<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AipActivityFundingEntryService
{
    public function create(array $input): int
    {
        $validated = Validator::make($input, [
            'aip_activity_id' => ['required', 'integer', 'min:1'],
            'vote_id' => ['required', 'integer', 'min:1'],
            'fund_id' => ['required', 'integer', 'min:1'],
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
                    'aip_activity_id' => 'Funding entries cannot be added to a closed financial year.',
                ]);
            }

            $voteExists = DB::table('votes')
                ->where('id', $validated['vote_id'])
                ->exists();

            if (! $voteExists) {
                throw ValidationException::withMessages([
                    'vote_id' => 'The selected vote does not exist.',
                ]);
            }

            $fundExists = DB::table('funds')
                ->where('id', $validated['fund_id'])
                ->exists();

            if (! $fundExists) {
                throw ValidationException::withMessages([
                    'fund_id' => 'The selected fund does not exist.',
                ]);
            }

            return DB::table('aip_activity_funding_entries')->insertGetId([
                'aip_activity_id' => $activity->id,
                'vote_id' => $validated['vote_id'],
                'fund_id' => $validated['fund_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}