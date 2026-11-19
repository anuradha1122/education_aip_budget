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
        Gate::define('manage-master-data', function (User $user): bool {
            return (bool) $user->is_admin;
        });

        Gate::define('import-aip', function (User $user): bool {
            return (bool) $user->is_admin && (bool) $user->can_import_aip;
        });
    }
}
