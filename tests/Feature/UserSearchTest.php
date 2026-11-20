<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_search_users_by_name_or_email(): void
    {
        $admin = User::factory()->create(['name' => 'Administrator']);
        $admin->forceFill(['is_admin' => true])->save();
        User::factory()->create(['name' => 'Kegalle Director', 'email' => 'director@example.test']);
        User::factory()->create(['name' => 'Ratnapura Officer', 'email' => 'ratnapura@example.test']);
        $this->actingAs($admin)->get('/user-management?search=Kegalle')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('search', 'Kegalle')->has('users.data', 1)->where('users.data.0.name', 'Kegalle Director'));
        $this->get('/user-management?search=ratnapura%40example.test')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)->where('users.data.0.name', 'Ratnapura Officer'));
        $this->get('/user-management?search=NoSuchUser')->assertOk()->assertInertia(fn (Assert $page) => $page->has('users.data', 0));
    }

    public function test_search_is_preserved_in_pagination_and_access_stays_restricted(): void
    {
        $this->get('/user-management?search=Find')->assertRedirect(route('login'));
        $user = User::factory()->create();
        $this->actingAs($user)->get('/user-management?search=Find')->assertForbidden();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        User::factory()->count(22)->sequence(fn ($sequence) => ['name' => 'Find '.$sequence->index])->create();
        $this->actingAs($admin)->get('/user-management?search=Find')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('users.total', 22)->has('users.data', 20)->where('search', 'Find')
            ->where('users.next_page_url', fn ($url) => str_contains($url, 'search=Find')));
        $this->get('/user-management?search=Find&page=2')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('users.total', 22)->has('users.data', 2)->where('search', 'Find'));
    }
}
