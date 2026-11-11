<?php

namespace App\Services;

use App\Models\Designation;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DesignationService
{
    public function create(array $data): Designation
    {
        return Designation::create($this->validatedData($data));
    }

    public function update(
        Designation $designation,
        array $data
    ): Designation {
        $designation->update(
            $this->validatedData($data, $designation)
        );

        return $designation->refresh();
    }

    public function deactivate(Designation $designation): Designation
    {
        return $this->update($designation, [
            'code' => $designation->code,
            'name' => $designation->name,
            'is_active' => false,
        ]);
    }

    private function validatedData(
        array $data,
        ?Designation $designation = null
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
                Rule::unique('designations', 'code')
                    ->ignore($designation?->id),
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