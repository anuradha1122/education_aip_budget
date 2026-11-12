<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Services\ExpenseCategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExpenseCategoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_category_and_normalises_details(): void
    {
        $category = app(ExpenseCategoryService::class)->create([
            'code' => ' test-01 ',
            'name' => ' Test category ',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'code' => 'TEST-01',
            'name' => 'Test category',
            'is_active' => true,
        ]);
    }

    public function test_it_rejects_a_duplicate_code_after_normalisation(): void
    {
        ExpenseCategory::create([
            'code' => 'TEST-01',
            'name' => 'First category',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(ExpenseCategoryService::class)->create([
            'code' => ' test-01 ',
            'name' => 'Another category',
            'is_active' => true,
        ]);
    }

    public function test_it_requires_a_name(): void
    {
        $this->expectException(ValidationException::class);

        app(ExpenseCategoryService::class)->create([
            'code' => 'TEST-01',
            'name' => '   ',
            'is_active' => true,
        ]);
    }

    public function test_it_allows_a_category_to_keep_its_code_when_updated(): void
    {
        $category = ExpenseCategory::create([
            'code' => 'TEST-01',
            'name' => 'Old name',
            'is_active' => true,
        ]);

        app(ExpenseCategoryService::class)->update($category, [
            'code' => ' test-01 ',
            'name' => 'New name',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'code' => 'TEST-01',
            'name' => 'New name',
        ]);
    }

    public function test_it_deactivates_a_category_without_deleting_it(): void
    {
        $category = ExpenseCategory::create([
            'code' => 'TEST-02',
            'name' => 'Test category',
            'is_active' => true,
        ]);

        app(ExpenseCategoryService::class)->deactivate($category);

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseCount('expense_categories', 1);
    }
}