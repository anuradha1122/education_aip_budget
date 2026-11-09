<?php

namespace App\Services;

use App\Models\Vote;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VoteService
{
    public function create(array $data): Vote
    {
        return Vote::create($this->validatedData($data));
    }

    public function update(Vote $vote, array $data): Vote
    {
        $vote->update($this->validatedData($data, $vote));

        return $vote->refresh();
    }

    public function deactivate(Vote $vote): Vote
    {
        return $this->update($vote, [
            'code' => $vote->code,
            'description' => $vote->description,
            'is_active' => false,
        ]);
    }

    private function validatedData(array $data, ?Vote $vote = null): array
    {
        if (isset($data['code']) && is_string($data['code'])) {
            $data['code'] = mb_strtoupper(trim($data['code']));
        }

        if (isset($data['description']) && is_string($data['description'])) {
            $data['description'] = trim($data['description']);
        }

        return Validator::make($data, [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('votes', 'code')->ignore($vote?->id),
            ],
            'description' => [
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