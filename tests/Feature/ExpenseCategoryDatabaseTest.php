<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseCategoryDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_expense_category_can_be_saved(): void
    {
        $category = ExpenseCategory::create([
            'code' => 'TEST-01',
            'name' => 'Test category',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'code' => 'TEST-01',
            'name' => 'Test category',
            'is_active' => true,
        ]);

        $this->assertTrue($category->is_active);
    }

    public function test_expense_category_codes_must_be_unique(): void
    {
        ExpenseCategory::create([
            'code' => 'TEST-01',
            'name' => 'First category',
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);

        ExpenseCategory::create([
            'code' => 'TEST-01',
            'name' => 'Another category',
            'is_active' => true,
        ]);
    }

    public function test_an_expense_category_can_be_marked_inactive(): void
    {
        $category = ExpenseCategory::create([
            'code' => 'TEST-02',
            'name' => 'Test category',
            'is_active' => true,
        ]);

        $category->update([
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'is_active' => false,
        ]);

        $this->assertFalse($category->fresh()->is_active);
    }
}