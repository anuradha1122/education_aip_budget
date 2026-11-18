<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AipActivityOfficeService
{
    /**
     * Link an office to a common AIP activity.
     *
     * Subject and officer are optional together. When supplied,
     * they must belong to the selected office and have an active
     * officer–subject assignment.
     */
    public function create(array $input, ?User $actor = null): int
    {
        $validated = Validator::make($input, [
            'aip_activity_id' => ['required', 'integer', 'min:1'],
            'office_id' => ['required', 'integer', 'min:1'],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'officer_id' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $subjectId = $validated['subject_id'] ?? null;
        $officerId = $validated['officer_id'] ?? null;

        if (($subjectId === null) !== ($officerId === null)) {
            throw ValidationException::withMessages([
                'subject_id' => 'Select both a subject and an officer, or leave both blank.',
                'officer_id' => 'Select both a subject and an officer, or leave both blank.',
            ]);
        }

        return DB::transaction(function () use (
            $validated,
            $subjectId,
            $officerId,
            $actor
        ) {
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

            if ($subjectId !== null && $officerId !== null) {
                // Match the officer-then-subject order used by
                // OfficerSubjectAssignmentService.
                $officer = DB::table('officers')
                    ->where('id', $officerId)
                    ->lockForUpdate()
                    ->first();

                $subject = DB::table('subjects')
                    ->where('id', $subjectId)
                    ->lockForUpdate()
                    ->first();

                if ($officer === null) {
                    throw ValidationException::withMessages([
                        'officer_id' => 'The selected officer does not exist.',
                    ]);
                }

                if ($subject === null) {
                    throw ValidationException::withMessages([
                        'subject_id' => 'The selected subject does not exist.',
                    ]);
                }

                if ((string) $officer->office_id !== (string) $office->id) {
                    throw ValidationException::withMessages([
                        'officer_id' => 'The officer must belong to the selected office.',
                    ]);
                }

                if ((string) $subject->office_id !== (string) $office->id) {
                    throw ValidationException::withMessages([
                        'subject_id' => 'The subject must belong to the selected office.',
                    ]);
                }

                $activeAssignment = DB::table('officer_subject_assignments')
                    ->where('officer_id', $officer->id)
                    ->where('subject_id', $subject->id)
                    ->whereNull('ended_at')
                    ->lockForUpdate()
                    ->first();

                if ($activeAssignment === null) {
                    throw ValidationException::withMessages([
                        'subject_id' => 'The officer must have an active assignment to the selected subject.',
                    ]);
                }
            }

            $linkId = DB::table('aip_activity_offices')->insertGetId([
                'aip_activity_id' => $activity->id,
                'office_id' => $office->id,
                'subject_id' => $subjectId,
                'officer_id' => $officerId,
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
                    'subject_id' => $subjectId,
                    'officer_id' => $officerId,
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $linkId;
        });
    }
}