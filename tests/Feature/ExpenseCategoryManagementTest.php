<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpenseCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_access_expense_categories(): void
    {
        $this->get('/expense-categories')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_manage_expense_categories(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/expense-categories')
            ->assertForbidden();

        $this->post('/expense-categories', [
            'code' => 'TEST-01',
            'name' => 'Test category',
            'is_active' => true,
        ])->assertForbidden();

        $this->assertDatabaseCount('expense_categories', 0);
    }

    public function test_an_administrator_can_view_and_search_categories(): void
    {
        ExpenseCategory::create([
            'code' => 'CAT-01',
            'name' => 'First category',
            'is_active' => true,
        ]);

        ExpenseCategory::create([
            'code' => 'CAT-02',
            'name' => 'Second category',
            'is_active' => true,
        ]);

        $this->actingAs($this->administrator())
            ->get('/expense-categories?search=CAT-01')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('expense-categories/index')
                ->where('filters.search', 'CAT-01')
                ->has('categories', 1)
                ->where('categories.0.code', 'CAT-01')
            );
    }

    public function test_an_administrator_can_create_a_category_with_an_audit_event(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)
            ->post('/expense-categories', [
                'code' => ' test-01 ',
                'name' => ' Test category ',
                'is_active' => true,
            ])
            ->assertRedirect(route('expense-categories.index'))
            ->assertSessionHasNoErrors();

        $category = ExpenseCategory::query()
            ->where('code', 'TEST-01')
            ->firstOrFail();

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'name' => 'Test category',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('expense_category_audit_events', [
            'expense_category_id' => $category->id,
            'actor_id' => $admin->id,
            'action' => 'created',
        ]);
    }

    public function test_an_administrator_can_update_and_deactivate_a_category(): void
    {
        $admin = $this->administrator();

        $category = ExpenseCategory::create([
            'code' => 'CAT-01',
            'name' => 'Old name',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put('/expense-categories/'.$category->id, [
                'code' => 'CAT-01',
                'name' => 'Updated name',
                'is_active' => false,
            ])
            ->assertRedirect(route('expense-categories.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'name' => 'Updated name',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('expense_category_audit_events', [
            'expense_category_id' => $category->id,
            'actor_id' => $admin->id,
            'action' => 'updated',
        ]);
    }

    private function administrator(): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'is_admin' => true,
        ])->save();

        return $user;
    }
}