<?php

namespace App\Services;

use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ExpenseCategoryService
{
    public function create(array $data): ExpenseCategory
    {
        return ExpenseCategory::create($this->validatedData($data));
    }

    public function update(
        ExpenseCategory $category,
        array $data
    ): ExpenseCategory {
        $category->update(
            $this->validatedData($data, $category)
        );

        return $category->refresh();
    }

    public function deactivate(ExpenseCategory $category): ExpenseCategory
    {
        return $this->update($category, [
            'code' => $category->code,
            'name' => $category->name,
            'is_active' => false,
        ]);
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
}