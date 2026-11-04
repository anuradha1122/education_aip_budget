<?php

namespace App\Services;

use App\Models\Fund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FundService
{
    public function create(array $data, ?User $actor = null): Fund
    {
        $validated = $this->validatedData($data);

        return DB::transaction(function () use ($validated, $actor) {
            $fund = Fund::create($validated);

            $this->recordAudit(
                $fund,
                $actor,
                'created',
                null,
                $this->values($fund)
            );

            return $fund;
        });
    }

    public function update(
        Fund $fund,
        array $data,
        ?User $actor = null
    ): Fund {
        $validated = $this->validatedData($data, $fund);

        return DB::transaction(function () use ($fund, $validated, $actor) {
            // Reload inside the transaction so the audit uses current values.
            $fund = Fund::query()->lockForUpdate()->findOrFail($fund->id);

            $oldValues = $this->values($fund);

            $fund->fill($validated);

            if (! $fund->isDirty(['code', 'name', 'is_active'])) {
                return $fund;
            }

            $fund->save();
            $fund->refresh();

            $this->recordAudit(
                $fund,
                $actor,
                'updated',
                $oldValues,
                $this->values($fund)
            );

            return $fund;
        });
    }

    public function deactivate(Fund $fund, ?User $actor = null): Fund
    {
        return $this->update($fund, [
            'code' => $fund->code,
            'name' => $fund->name,
            'is_active' => false,
        ], $actor);
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

    private function values(Fund $fund): array
    {
        return [
            'code' => $fund->code,
            'name' => $fund->name,
            'is_active' => (bool) $fund->is_active,
        ];
    }

    private function recordAudit(
        Fund $fund,
        ?User $actor,
        string $action,
        ?array $oldValues,
        array $newValues
    ): void {
        DB::table('fund_audit_events')->insert([
            'fund_id' => $fund->id,
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
