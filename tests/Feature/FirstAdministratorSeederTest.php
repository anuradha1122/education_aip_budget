<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\FirstUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FirstAdministratorSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_first_administrator_in_local_environment(): void
    {
        $this->app['env'] = 'local';

        $this->artisan('db:seed', [
            '--class' => FirstUserSeeder::class,
        ])
            ->expectsQuestion(
                'Enter the administrator name',
                'Local Administrator'
            )
            ->expectsQuestion(
                'Enter the administrator email',
                'ADMIN@example.com'
            )
            ->expectsQuestion(
                'Enter a password',
                'example-password'
            )
            ->expectsQuestion(
                'Confirm the password',
                'example-password'
            )
            ->assertExitCode(0);

        $user = User::where('email', 'admin@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue((bool) $user->is_admin);
        $this->assertTrue(
            Hash::check('example-password', $user->password)
        );
    }

    public function test_it_does_not_create_another_administrator(): void
    {
        $this->app['env'] = 'local';

        $existingAdmin = User::factory()->create();

        $existingAdmin->forceFill([
            'is_admin' => true,
        ])->save();

        $this->artisan('db:seed', [
            '--class' => FirstUserSeeder::class,
        ])->assertExitCode(0);

        $this->assertSame(1, User::count());
        $this->assertSame(
            1,
            User::where('is_admin', true)->count()
        );
    }

    public function test_it_does_not_create_an_account_outside_local_environment(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:seed', [
            '--class' => FirstUserSeeder::class,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, User::count());
    }
}
