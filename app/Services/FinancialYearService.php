<?php

namespace App\Services;

use App\Models\FinancialYear;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinancialYearService
{
    public function create(array $data, ?User $actor = null): FinancialYear
    {
        $validated = $this->validatedData($data);

        $validated['is_closed'] = false;

        return DB::transaction(function () use ($validated, $actor) {
            $financialYear = FinancialYear::create($validated);

            $this->recordAudit(
                $financialYear,
                $actor,
                'created',
                null,
                $this->values($financialYear)
            );

            return $financialYear;
        });
    }

    public function update(
        FinancialYear $financialYear,
        array $data,
        ?User $actor = null
    ): FinancialYear {
        return DB::transaction(function () use ($financialYear, $data, $actor) {
            $financialYear = FinancialYear::query()
                ->lockForUpdate()
                ->findOrFail($financialYear->id);

            if ($financialYear->is_closed) {
                throw ValidationException::withMessages([
                    'year' => 'A closed financial year cannot be edited.',
                ]);
            }

            $validated = $this->validatedData($data, $financialYear);
            $oldValues = $this->values($financialYear);

            $financialYear->fill($validated);

            if (! $financialYear->isDirty(['year', 'start_date', 'end_date'])) {
                return $financialYear;
            }

            $financialYear->save();
            $financialYear->refresh();

            $this->recordAudit(
                $financialYear,
                $actor,
                'updated',
                $oldValues,
                $this->values($financialYear)
            );

            return $financialYear;
        });
    }

    private function validatedData(
        array $data,
        ?FinancialYear $financialYear = null
    ): array {
        return Validator::make($data, [
            'year' => [
                'required',
                'integer',
                'min:1',
                'max:65535',
                Rule::unique('financial_years', 'year')
                    ->ignore($financialYear?->id),
            ],
            'start_date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'end_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
            ],
        ])->validate();
    }

    private function values(FinancialYear $financialYear): array
    {
        return [
            'year' => $financialYear->year,
            'start_date' => $financialYear->start_date->toDateString(),
            'end_date' => $financialYear->end_date->toDateString(),
            'is_closed' => $financialYear->is_closed,
        ];
    }

    private function recordAudit(
        FinancialYear $financialYear,
        ?User $actor,
        string $action,
        ?array $oldValues,
        array $newValues
    ): void {
        DB::table('financial_year_audit_events')->insert([
            'financial_year_id' => $financialYear->id,
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