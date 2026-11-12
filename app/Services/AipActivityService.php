<?php

namespace App\Services;

use App\Models\AipActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AipActivityService
{
    public function create(array $input, ?User $actor = null): AipActivity
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
            'subject_id' => ['nullable', 'integer', 'min:1', 'required_with:officer_id'],
            'officer_id' => ['nullable', 'integer', 'min:1', 'required_with:subject_id'],
        ])->validate();

        return DB::transaction(function () use ($validated, $actor) {
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

            $subjectId = $validated['subject_id'] ?? null;
            $officerId = $validated['officer_id'] ?? null;

            if ($subjectId !== null && $officerId !== null) {
                $officer = DB::table('officers')->where('id', $officerId)->lockForUpdate()->first();
                $subject = DB::table('subjects')->where('id', $subjectId)->lockForUpdate()->first();

                if ($officer === null) {
                    throw ValidationException::withMessages(['officer_id' => 'The selected officer does not exist.']);
                }
                if ($subject === null) {
                    throw ValidationException::withMessages(['subject_id' => 'The selected subject does not exist.']);
                }
                if ((string) $officer->office_id !== (string) $subject->office_id) {
                    throw ValidationException::withMessages(['subject_id' => 'The subject and officer must belong to the same office.']);
                }
                $assignment = DB::table('officer_subject_assignments')
                    ->where('officer_id', $officerId)->where('subject_id', $subjectId)
                    ->whereNull('ended_at')->lockForUpdate()->first();
                if ($assignment === null) {
                    throw ValidationException::withMessages(['subject_id' => 'The officer is not assigned to this subject.']);
                }
            }

            $activity = AipActivity::create([
                'financial_year_id' => $year->id,
                'activity_number' => $validated['activity_number'],
                'description' => $validated['description'],
                'location' => ($validated['location'] ?? '') === '' ? null : $validated['location'],
                'subject_id' => $subjectId,
                'officer_id' => $officerId,
            ]);

            DB::table('aip_activity_audit_events')->insert([
                'aip_activity_id' => $activity->id,
                'actor_id' => $actor?->id,
                'action' => 'created',
                'new_values' => json_encode([
                    'financial_year_id' => $activity->financial_year_id,
                    'activity_number' => $activity->activity_number,
                    'description' => $activity->description,
                    'location' => $activity->location,
                    'subject_id' => $activity->subject_id,
                    'officer_id' => $activity->officer_id,
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $activity;
        });
    }
}
