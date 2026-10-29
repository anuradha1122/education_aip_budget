<?php

namespace App\Services;

use App\Models\Office;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OfficeService
{
    /**
     * Create an office after validating its details.
     *
     * The caller is responsible for authorising the operation.
     */
    public function create(array $data): Office
    {
        $validated = $this->validate($data);

        return Office::create($validated);
    }

    /**
     * Update an office after validating its details.
     *
     * The caller is responsible for authorising the operation.
     */
    public function update(Office $office, array $data): Office
    {
        $validated = $this->validate($data, $office);

        $office->update($validated);

        return $office->fresh();
    }

    /**
     * Validate and normalise office details.
     */
    private function validate(array $data, ?Office $office = null): array
    {
        if (isset($data['code']) && is_string($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        if (isset($data['name']) && is_string($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        return Validator::make($data, [
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('offices', 'code')->ignore($office?->id),
            ],
            'name' => [
                'required',
                'string',
                'max:150',
            ],
            'type' => [
                'required',
                Rule::in(['provincial', 'zonal']),
            ],
            'is_active' => [
                'required',
                'boolean',
            ],
        ])->validate();
    }
}
