<?php

namespace App\Services;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SubjectService
{
    public function create(array $data, ?User $actor = null): Subject
    {
        $validated = $this->validatedData($data);

        return DB::transaction(function () use ($validated, $actor) {
            $subject = Subject::create($validated);

            $this->recordAudit(
                $subject,
                $actor,
                'created',
                null,
                $this->values($subject)
            );

            return $subject;
        });
    }

    public function update(
        Subject $subject,
        array $data,
        ?User $actor = null
    ): Subject {
        return DB::transaction(function () use ($subject, $data, $actor) {
            $subject = Subject::query()
                ->lockForUpdate()
                ->findOrFail($subject->id);

            $validated = $this->validatedData($data, $subject);
            $oldValues = $this->values($subject);

            $subject->fill($validated);

            if (! $subject->isDirty(['office_id', 'code', 'name', 'is_active'])) {
                return $subject;
            }

            $subject->save();
            $subject->refresh();

            $this->recordAudit(
                $subject,
                $actor,
                'updated',
                $oldValues,
                $this->values($subject)
            );

            return $subject;
        });
    }

    public function deactivate(Subject $subject, ?User $actor = null): Subject
    {
        return $this->update($subject, [
            'office_id' => $subject->office_id,
            'code' => $subject->code,
            'name' => $subject->name,
            'is_active' => false,
        ], $actor);
    }

    private function validatedData(
        array $data,
        ?Subject $subject = null
    ): array {
        if (isset($data['code']) && is_string($data['code'])) {
            $data['code'] = mb_strtoupper(trim($data['code']));
        }

        if (isset($data['name']) && is_string($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        return Validator::make($data, [
            'office_id' => [
                'required',
                'integer',
                Rule::exists('offices', 'id'),
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('subjects', 'code')
                    ->where('office_id', $data['office_id'] ?? null)
                    ->ignore($subject?->id),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'is_active' => [
                'required',
                'boolean',
            ],
        ])->validate();
    }

    private function values(Subject $subject): array
    {
        return [
            'office_id' => $subject->office_id,
            'code' => $subject->code,
            'name' => $subject->name,
            'is_active' => (bool) $subject->is_active,
        ];
    }

    private function recordAudit(
        Subject $subject,
        ?User $actor,
        string $action,
        ?array $oldValues,
        array $newValues
    ): void {
        DB::table('subject_audit_events')->insert([
            'subject_id' => $subject->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'old_values' => $oldValues === null
                ? null
                : json_encode($oldValues, JSON_THROW_ON_ERROR),
            'new_values' => json_encode($newValues, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}