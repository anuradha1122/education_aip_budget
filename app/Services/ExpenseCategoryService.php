<?php

namespace App\Services;

use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ExpenseCategoryService
{
    public function create(
        array $data,
        ?User $actor = null
    ): ExpenseCategory {
        $validated = $this->validatedData($data);

        return DB::transaction(function () use ($validated, $actor) {
            $category = ExpenseCategory::create($validated);

            $this->recordAudit(
                $category,
                $actor,
                'created',
                null,
                $this->values($category)
            );

            return $category;
        });
    }

    public function update(
        ExpenseCategory $category,
        array $data,
        ?User $actor = null
    ): ExpenseCategory {
        return DB::transaction(function () use ($category, $data, $actor) {
            $category = ExpenseCategory::query()
                ->lockForUpdate()
                ->findOrFail($category->id);

            $validated = $this->validatedData($data, $category);
            $oldValues = $this->values($category);

            $category->fill($validated);

            if (! $category->isDirty(['code', 'name', 'is_active'])) {
                return $category;
            }

            $category->save();
            $category->refresh();

            $this->recordAudit(
                $category,
                $actor,
                'updated',
                $oldValues,
                $this->values($category)
            );

            return $category;
        });
    }

    public function deactivate(
        ExpenseCategory $category,
        ?User $actor = null
    ): ExpenseCategory {
        return DB::transaction(function () use ($category, $actor) {
            $category = ExpenseCategory::query()
                ->lockForUpdate()
                ->findOrFail($category->id);

            return $this->update($category, [
                'code' => $category->code,
                'name' => $category->name,
                'is_active' => false,
            ], $actor);
        });
    }

    private function validatedData(
        array $data,
        ?ExpenseCategory $category = null
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
                Rule::unique('expense_categories', 'code')
                    ->ignore($category?->id),
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

    private function values(ExpenseCategory $category): array
    {
        return [
            'code' => $category->code,
            'name' => $category->name,
            'is_active' => (bool) $category->is_active,
        ];
    }

    private function recordAudit(
        ExpenseCategory $category,
        ?User $actor,
        string $action,
        ?array $oldValues,
        array $newValues
    ): void {
        DB::table('expense_category_audit_events')->insert([
            'expense_category_id' => $category->id,
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