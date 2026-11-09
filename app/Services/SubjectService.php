<?php

namespace App\Services;

use App\Models\Subject;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SubjectService
{
    public function create(array $data): Subject
    {
        return Subject::create($this->validatedData($data));
    }

    public function update(Subject $subject, array $data): Subject
    {
        $subject->update($this->validatedData($data, $subject));

        return $subject->refresh();
    }

    public function deactivate(Subject $subject): Subject
    {
        return $this->update($subject, [
            'office_id' => $subject->office_id,
            'code' => $subject->code,
            'name' => $subject->name,
            'is_active' => false,
        ]);
    }

    private function validatedData(array $data, ?Subject $subject = null): array
    {
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
}