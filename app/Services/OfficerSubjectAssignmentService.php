<?php

namespace App\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OfficerSubjectAssignmentService
{
    public function assign(array $input): int
    {
        $data = Validator::make($input, [
            'officer_id' => ['required', 'integer', 'min:1'],
            'subject_id' => ['required', 'integer', 'min:1'],
        ])->validate();

        try {
            return DB::transaction(function () use ($data) {
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

                return DB::table('officer_subject_assignments')->insertGetId([
                    'officer_id' => $officer->id,
                    'subject_id' => $subject->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['subject_id' => 'This officer is already assigned to this subject.']);
        }
    }
}
