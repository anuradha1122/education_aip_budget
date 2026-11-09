<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VoteService
{
    public function create(array $data, ?User $actor = null): Vote
    {
        $validated = $this->validatedData($data);

        return DB::transaction(function () use ($validated, $actor) {
            $vote = Vote::create($validated);

            $this->recordAudit(
                $vote,
                $actor,
                'created',
                null,
                $this->values($vote)
            );

            return $vote;
        });
    }

    public function update(
        Vote $vote,
        array $data,
        ?User $actor = null
    ): Vote {
        $validated = $this->validatedData($data, $vote);

        return DB::transaction(function () use ($vote, $validated, $actor) {
            $vote = Vote::query()
                ->lockForUpdate()
                ->findOrFail($vote->id);

            $oldValues = $this->values($vote);

            $vote->fill($validated);

            if (! $vote->isDirty(['code', 'description', 'is_active'])) {
                return $vote;
            }

            $vote->save();
            $vote->refresh();

            $this->recordAudit(
                $vote,
                $actor,
                'updated',
                $oldValues,
                $this->values($vote)
            );

            return $vote;
        });
    }

    public function deactivate(Vote $vote, ?User $actor = null): Vote
    {
        return $this->update($vote, [
            'code' => $vote->code,
            'description' => $vote->description,
            'is_active' => false,
        ], $actor);
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

    private function values(Vote $vote): array
    {
        return [
            'code' => $vote->code,
            'description' => $vote->description,
            'is_active' => (bool) $vote->is_active,
        ];
    }

    private function recordAudit(
        Vote $vote,
        ?User $actor,
        string $action,
        ?array $oldValues,
        array $newValues
    ): void {
        DB::table('vote_audit_events')->insert([
            'vote_id' => $vote->id,
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