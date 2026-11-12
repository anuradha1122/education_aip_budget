<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\ExpenseCategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExpenseCategoryAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_is_recorded_with_the_actor(): void
    {
        $actor = User::factory()->create();

        $category = app(ExpenseCategoryService::class)->create([
            'code' => 'TEST-01',
            'name' => 'Test category',
            'is_active' => true,
        ], $actor);

        $this->assertDatabaseHas('expense_category_audit_events', [
            'expense_category_id' => $category->id,
            'actor_id' => $actor->id,
            'action' => 'created',
            'old_values' => null,
        ]);

        $event = DB::table('expense_category_audit_events')
            ->where('expense_category_id', $category->id)
            ->first();

        $newValues = json_decode($event->new_values, true);

        $this->assertSame('TEST-01', $newValues['code']);
        $this->assertSame('Test category', $newValues['name']);
        $this->assertTrue($newValues['is_active']);
    }

    public function test_update_records_previous_and_new_values(): void
    {
        $actor = User::factory()->create();

        $category = ExpenseCategory::create([
            'code' => 'TEST-01',
            'name' => 'Old name',
            'is_active' => true,
        ]);

        app(ExpenseCategoryService::class)->update($category, [
            'code' => 'TEST-01',
            'name' => 'New name',
            'is_active' => false,
        ], $actor);

        $this->assertDatabaseHas('expense_category_audit_events', [
            'expense_category_id' => $category->id,
            'actor_id' => $actor->id,
            'action' => 'updated',
        ]);

        $event = DB::table('expense_category_audit_events')
            ->where('expense_category_id', $category->id)
            ->first();

        $oldValues = json_decode($event->old_values, true);
        $newValues = json_decode($event->new_values, true);

        $this->assertSame('Old name', $oldValues['name']);
        $this->assertTrue($oldValues['is_active']);
        $this->assertSame('New name', $newValues['name']);
        $this->assertFalse($newValues['is_active']);
    }

    public function test_an_unchanged_category_does_not_create_an_event(): void
    {
        $category = ExpenseCategory::create([
            'code' => 'TEST-01',
            'name' => 'Test category',
            'is_active' => true,
        ]);

        app(ExpenseCategoryService::class)->update($category, [
            'code' => ' test-01 ',
            'name' => ' Test category ',
            'is_active' => true,
        ]);

        $this->assertDatabaseCount('expense_category_audit_events', 0);
    }
}