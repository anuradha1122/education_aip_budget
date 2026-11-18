<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AipActivityFundingEntryService
{
    public function create(array $input, ?User $actor = null): int
    {
        $validated = Validator::make($input, [
            'aip_activity_id' => ['required', 'integer', 'min:1'],
            'vote_id' => ['required', 'integer', 'min:1'],
            'fund_id' => ['required', 'integer', 'min:1'],
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

            // Lock the year before checking and creating funding entries.
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

            $differentFundExists = DB::table('aip_activity_funding_entries')
                ->where('aip_activity_id', $activity->id)
                ->where('fund_id', '<>', $validated['fund_id'])
                ->exists();

            if ($differentFundExists) {
                throw ValidationException::withMessages([
                    'fund_id' => 'This activity already has a different fund. An activity can have only one fund.',
                ]);
            }

            $differentVoteExists = DB::table('aip_activity_funding_entries')
                ->where('aip_activity_id', $activity->id)
                ->where('vote_id', '<>', $validated['vote_id'])
                ->exists();

            if ($differentVoteExists) {
                throw ValidationException::withMessages([
                    'vote_id' => 'This activity already has a different vote. All offices must use the same vote for the activity.',
                ]);
            }

            $entryId = DB::table('aip_activity_funding_entries')->insertGetId([
                'aip_activity_id' => $activity->id,
                'vote_id' => $validated['vote_id'],
                'fund_id' => $validated['fund_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('aip_funding_entry_audit_events')->insert([
                'funding_entry_id' => $entryId,
                'actor_id' => $actor?->id,
                'action' => 'created',
                'new_values' => json_encode([
                    'aip_activity_id' => $activity->id,
                    'financial_year_id' => $year->id,
                    'vote_id' => (int) $validated['vote_id'],
                    'fund_id' => (int) $validated['fund_id'],
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $entryId;
        });
    }
}
