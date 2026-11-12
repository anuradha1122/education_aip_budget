<?php

namespace App\Services;

use App\Models\ProgrammeType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProgrammeTypeService
{
    public function create(array $data): ProgrammeType
    {
        return ProgrammeType::create($this->validatedData($data));
    }

    public function update(
        ProgrammeType $programmeType,
        array $data
    ): ProgrammeType {
        $programmeType->update(
            $this->validatedData($data, $programmeType)
        );

        return $programmeType->refresh();
    }

    public function deactivate(ProgrammeType $programmeType): ProgrammeType
    {
        return $this->update($programmeType, [
            'code' => $programmeType->code,
            'name' => $programmeType->name,
            'is_active' => false,
        ]);
    }

    private function validatedData(
        array $data,
        ?ProgrammeType $programmeType = null
    ): array {
        if (isset($data['code']) && is_string($data['code'])) {
            $data['code'] = mb_strtoupper(trim($data['code']));
        }

        if (isset($data['name']) && is_string($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        return Validator::make($data, [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('programme_types', 'code')
                    ->ignore($programmeType?->id),
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