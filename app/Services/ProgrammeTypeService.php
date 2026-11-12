<?php

namespace App\Services;

use App\Models\ProgrammeType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProgrammeTypeService
{
    public function create(
        array $data,
        ?User $actor = null
    ): ProgrammeType {
        $validated = $this->validatedData($data);

        return DB::transaction(function () use ($validated, $actor) {
            $programmeType = ProgrammeType::create($validated);

            $this->recordAudit(
                $programmeType,
                $actor,
                'created',
                null,
                $this->values($programmeType)
            );

            return $programmeType;
        });
    }

    public function update(
        ProgrammeType $programmeType,
        array $data,
        ?User $actor = null
    ): ProgrammeType {
        return DB::transaction(function () use ($programmeType, $data, $actor) {
            $programmeType = ProgrammeType::query()
                ->lockForUpdate()
                ->findOrFail($programmeType->id);

            $validated = $this->validatedData($data, $programmeType);
            $oldValues = $this->values($programmeType);

            $programmeType->fill($validated);

            if (! $programmeType->isDirty(['code', 'name', 'is_active'])) {
                return $programmeType;
            }

            $programmeType->save();
            $programmeType->refresh();

            $this->recordAudit(
                $programmeType,
                $actor,
                'updated',
                $oldValues,
                $this->values($programmeType)
            );

            return $programmeType;
        });
    }

    public function deactivate(
        ProgrammeType $programmeType,
        ?User $actor = null
    ): ProgrammeType {
        return DB::transaction(function () use ($programmeType, $actor) {
            $programmeType = ProgrammeType::query()
                ->lockForUpdate()
                ->findOrFail($programmeType->id);

            return $this->update($programmeType, [
                'code' => $programmeType->code,
                'name' => $programmeType->name,
                'is_active' => false,
            ], $actor);
        });
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

    private function values(ProgrammeType $programmeType): array
    {
        return [
            'code' => $programmeType->code,
            'name' => $programmeType->name,
            'is_active' => (bool) $programmeType->is_active,
        ];
    }

    private function recordAudit(
        ProgrammeType $programmeType,
        ?User $actor,
        string $action,
        ?array $oldValues,
        array $newValues
    ): void {
        DB::table('programme_type_audit_events')->insert([
            'programme_type_id' => $programmeType->id,
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