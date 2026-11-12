<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ExpenseCategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpenseCategoryAuditHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_view_expense_category_audit_history(): void
    {
        $this->get('/expense-categories/audit')->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_view_expense_category_audit_history(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/expense-categories/audit')->assertForbidden();
    }

    public function test_an_administrator_can_view_expense_category_audit_history(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        app(ExpenseCategoryService::class)->create([
            'code' => 'AUD-01',
            'name' => 'Audit test category',
            'is_active' => true,
        ], $admin);

        $this->actingAs($admin)->get('/expense-categories/audit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('expense-categories/audit')
                ->has('events.data', 1)
                ->where('events.data.0.category_code', 'AUD-01')
                ->where('events.data.0.category_name', 'Audit test category')
                ->where('events.data.0.actor_name', $admin->name)
                ->where('events.data.0.action', 'created')
                ->has('events.data.0.new_values')
            );
    }
}
