<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Record the access generation on every successful login, including remember-me.
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function ($event): void {
            if ($event->guard === 'web' && request()->hasSession()) {
                request()->session()->put('account_access_version', (int) $event->user->access_version);
            }
        });

        Gate::define('manage-master-data', function (User $user): bool {
            return (bool) $user->is_admin;
        });

        Gate::define('manage-financial-years', function (User $user): bool {
            if ((bool) $user->is_admin) {
                return true;
            }

            $scope = app(\App\Services\AipRegisterScope::class)->forUser($user);

            return $scope !== null && $scope->role === 'provincial_admin';
        });

        Gate::define('manage-organisational-data', function (User $user): bool {
            if ((bool) $user->is_admin) {
                return true;
            }

            $scope = app(\App\Services\AipRegisterScope::class)->forUser($user);

            return $scope !== null && $scope->role === 'provincial_admin';
        });

        Gate::define('view-aip-register', function (User $user): bool {
            if ((bool) $user->is_admin) {
                return true;
            }

            return app(\App\Services\AipRegisterScope::class)->forUser($user) !== null;
        });

        Gate::define('create-aip-activity', function (User $user): bool {
            if ((bool) $user->is_admin) {
                return true;
            }
            $scope = app(\App\Services\AipRegisterScope::class)->forUser($user);
            return $scope !== null && $scope->role === 'provincial_admin';
        });

        Gate::define('manage-aip-assignments', function (User $user): bool {
            if ((bool) $user->is_admin) {
                return true;
            }

            $scope = app(\App\Services\AipRegisterScope::class)->forUser($user);

            return $scope !== null && $scope->role === 'provincial_admin';
        });

        Gate::define('import-aip', function (User $user): bool {
            if (! (bool) $user->can_import_aip) {
                return false;
            }

            if ((bool) $user->is_admin) {
                return true;
            }

            $scope = app(\App\Services\AipRegisterScope::class)->forUser($user);

            return $scope !== null && $scope->role === 'provincial_admin';
        });
    }
}
