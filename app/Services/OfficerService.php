<?php

namespace App\Services;

use App\Models\Officer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OfficerService
{
    public function create(
        array $data,
        ?User $actor = null
    ): Officer {
        $validated = $this->validatedData($data);

        return DB::transaction(function () use ($validated, $actor) {
            $officer = Officer::create($validated);

            $this->recordAudit(
                $officer,
                $actor,
                'created',
                null,
                $this->values($officer)
            );

            return $officer;
        });
    }

    public function update(
        Officer $officer,
        array $data,
        ?User $actor = null
    ): Officer {
        return DB::transaction(function () use ($officer, $data, $actor) {
            $officer = Officer::query()
                ->lockForUpdate()
                ->findOrFail($officer->id);

            $validated = $this->validatedData($data);
            $oldValues = $this->values($officer);

            $officer->fill($validated);

            if (! $officer->isDirty([
                'office_id',
                'designation_id',
                'name',
                'is_active',
            ])) {
                return $officer;
            }

            $officer->save();
            $officer->refresh();

            $this->recordAudit(
                $officer,
                $actor,
                'updated',
                $oldValues,
                $this->values($officer)
            );

            return $officer;
        });
    }

    public function deactivate(
        Officer $officer,
        ?User $actor = null
    ): Officer {
        return DB::transaction(function () use ($officer, $actor) {
            $officer = Officer::query()
                ->lockForUpdate()
                ->findOrFail($officer->id);

            return $this->update($officer, [
                'office_id' => $officer->office_id,
                'designation_id' => $officer->designation_id,
                'name' => $officer->name,
                'is_active' => false,
            ], $actor);
        });
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

    private function values(Officer $officer): array
    {
        return [
            'office_id' => $officer->office_id,
            'designation_id' => $officer->designation_id,
            'name' => $officer->name,
            'is_active' => (bool) $officer->is_active,
        ];
    }

    private function recordAudit(
        Officer $officer,
        ?User $actor,
        string $action,
        ?array $oldValues,
        array $newValues
    ): void {
        DB::table('officer_audit_events')->insert([
            'officer_id' => $officer->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'old_values' => $oldValues === null
                ? null
                : json_encode($oldValues, JSON_THROW_ON_ERROR),
            'new_values' => json_encode($newValues, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}