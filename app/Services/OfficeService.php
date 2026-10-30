<?php

namespace App\Services;

use App\Models\Office;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OfficeService
{
    /**
     * Create an office and record its audit event.
     *
     * The caller is responsible for authorising the operation.
     */
    public function create(array $data): Office
    {
        $validated = $this->validate($data);

        return DB::transaction(function () use ($validated) {
            $office = Office::create($validated);

            $this->recordAudit(
                $office,
                'created',
                null,
                $this->auditValues($office)
            );

            return $office;
        });
    }

    /**
     * Update an office and record changes.
     *
     * The caller is responsible for authorising the operation.
     */
    public function update(Office $office, array $data): Office
    {
        $validated = $this->validate($data, $office);

        return DB::transaction(function () use ($office, $validated) {
            $before = $this->auditValues($office);

            $office->fill($validated);

            if (! $office->isDirty()) {
                return $office->fresh();
            }

            $office->save();

            $this->recordAudit(
                $office,
                'updated',
                $before,
                $this->auditValues($office)
            );

            return $office->fresh();
        });
    }

    /**
     * Select the office values recorded in the audit history.
     */
    private function auditValues(Office $office): array
    {
        return [
            'code' => $office->code,
            'name' => $office->name,
            'type' => $office->type,
            'is_active' => (bool) $office->is_active,
        ];
    }

    /**
     * Store an audit event in the same transaction as the office change.
     */
    private function recordAudit(
        Office $office,
        string $action,
        ?array $oldValues,
        array $newValues
    ): void {
        DB::table('office_audit_events')->insert([
            'office_id' => $office->id,
            'actor_id' => Auth::id(),
            'action' => $action,
            'old_values' => $oldValues === null
                ? null
                : json_encode($oldValues, JSON_THROW_ON_ERROR),
            'new_values' => json_encode(
                $newValues,
                JSON_THROW_ON_ERROR
            ),
            'created_at' => now(),
        ]);
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
