<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OfficerSubjectAssignmentService
{
    public function assign(array $input, ?User $actor = null): int
    {
        $data = Validator::make($input, [
            'officer_id' => ['required', 'integer', 'min:1'],
            'subject_id' => ['required', 'integer', 'min:1'],
        ])->validate();

        try {
            return DB::transaction(function () use ($data, $actor) {
                $officer = DB::table('officers')->where('id', $data['officer_id'])->lockForUpdate()->first();
                $subject = DB::table('subjects')->where('id', $data['subject_id'])->lockForUpdate()->first();

                if ($officer === null) {
                    throw ValidationException::withMessages(['officer_id' => 'The selected officer does not exist.']);
                }
                if ($subject === null) {
                    throw ValidationException::withMessages(['subject_id' => 'The selected subject does not exist.']);
                }
                if ((string) $officer->office_id !== (string) $subject->office_id) {
                    throw ValidationException::withMessages(['subject_id' => 'The subject and officer must belong to the same office.']);
                }

                $assignmentId = DB::table('officer_subject_assignments')->insertGetId([
                    'officer_id' => $officer->id,
                    'subject_id' => $subject->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('officer_subject_assignment_audit_events')->insert([
                    'assignment_id' => $assignmentId,
                    'actor_id' => $actor?->id,
                    'action' => 'assigned',
                    'officer_id' => $officer->id,
                    'subject_id' => $subject->id,
                    'office_id' => $officer->office_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $assignmentId;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['subject_id' => 'This officer is already assigned to this subject.']);
        }
    }
    public function end(int $assignmentId, ?User $actor = null): void
    {
        DB::transaction(function () use ($assignmentId, $actor) {
            // Match the lock order used when creating an assignment.
            $reference = DB::table('officer_subject_assignments')->where('id', $assignmentId)->first();
            if ($reference === null) {
                throw ValidationException::withMessages(['assignment' => 'The assignment does not exist.']);
            }
            DB::table('officers')->where('id', $reference->officer_id)->lockForUpdate()->first();
            DB::table('subjects')->where('id', $reference->subject_id)->lockForUpdate()->first();
            $assignment = DB::table('officer_subject_assignments')->where('id', $assignmentId)->lockForUpdate()->first();
            if ($assignment === null || $assignment->ended_at !== null) {
                throw ValidationException::withMessages(['assignment' => 'This assignment has already ended or no longer exists.']);
            }

            // Use the recorded assignment office where available; earlier records may lack an event.
            $officeId = DB::table('officer_subject_assignment_audit_events')
                ->where('assignment_id', $assignmentId)->where('action', 'assigned')->orderBy('id')->value('office_id');
            if ($officeId === null) {
                $officer = DB::table('officers')->where('id', $assignment->officer_id)->first();
                $officeId = $officer->office_id;
            }
            $now = now();
            DB::table('officer_subject_assignments')->where('id', $assignmentId)->update([
                'ended_at' => $now, 'active_marker' => null, 'updated_at' => $now,
            ]);
            DB::table('officer_subject_assignment_audit_events')->insert([
                'assignment_id' => $assignmentId, 'actor_id' => $actor?->id,
                'action' => 'ended', 'officer_id' => $assignment->officer_id,
                'subject_id' => $assignment->subject_id, 'office_id' => $officeId,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        });
    }

}
