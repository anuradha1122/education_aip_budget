<?php

namespace App\Services;

use App\Models\Designation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DesignationService
{
    public function create(
        array $data,
        ?User $actor = null
    ): Designation {
        $validated = $this->validatedData($data);

        return DB::transaction(function () use ($validated, $actor) {
            $designation = Designation::create($validated);

            $this->recordAudit(
                $designation,
                $actor,
                'created',
                null,
                $this->values($designation)
            );

            return $designation;
        });
    }

    public function update(
        Designation $designation,
        array $data,
        ?User $actor = null
    ): Designation {
        return DB::transaction(function () use ($designation, $data, $actor) {
            $designation = Designation::query()
                ->lockForUpdate()
                ->findOrFail($designation->id);

            $validated = $this->validatedData($data, $designation);
            $oldValues = $this->values($designation);

            $designation->fill($validated);

            if (! $designation->isDirty(['code', 'name', 'is_active'])) {
                return $designation;
            }

            $designation->save();
            $designation->refresh();

            $this->recordAudit(
                $designation,
                $actor,
                'updated',
                $oldValues,
                $this->values($designation)
            );

            return $designation;
        });
    }

    public function deactivate(
        Designation $designation,
        ?User $actor = null
    ): Designation {
        return $this->update($designation, [
            'code' => $designation->code,
            'name' => $designation->name,
            'is_active' => false,
        ], $actor);
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

    private function values(Designation $designation): array
    {
        return [
            'code' => $designation->code,
            'name' => $designation->name,
            'is_active' => (bool) $designation->is_active,
        ];
    }

    private function recordAudit(
        Designation $designation,
        ?User $actor,
        string $action,
        ?array $oldValues,
        array $newValues
    ): void {
        DB::table('designation_audit_events')->insert([
            'designation_id' => $designation->id,
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