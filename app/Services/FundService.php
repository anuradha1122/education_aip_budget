<?php

namespace App\Services;

use App\Models\Fund;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FundService
{
    public function create(array $data): Fund
    {
        return Fund::create($this->validatedData($data));
    }

    public function update(Fund $fund, array $data): Fund
    {
        $fund->update($this->validatedData($data, $fund));

        return $fund->refresh();
    }

    public function deactivate(Fund $fund): Fund
    {
        $fund->update([
            'is_active' => false,
        ]);

        return $fund->refresh();
    }

    private function validatedData(array $data, ?Fund $fund = null): array
    {
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
                Rule::unique('funds', 'code')->ignore($fund?->id),
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
