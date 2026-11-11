<?php

namespace App\Services;

use App\Models\Officer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OfficerService
{
    public function create(array $data): Officer
    {
        return Officer::create($this->validatedData($data));
    }

    public function update(Officer $officer, array $data): Officer
    {
        $officer->update($this->validatedData($data));

        return $officer->refresh();
    }

    public function deactivate(Officer $officer): Officer
    {
        return $this->update($officer, [
            'office_id' => $officer->office_id,
            'designation_id' => $officer->designation_id,
            'name' => $officer->name,
            'is_active' => false,
        ]);
    }

    private function validatedData(array $data): array
    {
        if (isset($data['name']) && is_string($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        return Validator::make($data, [
            'office_id' => [
                'required',
                'integer',
                Rule::exists('offices', 'id'),
            ],
            'designation_id' => [
                'required',
                'integer',
                Rule::exists('designations', 'id'),
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