<?php

namespace App\Modules\BasicMedia\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BasicMediaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'App\Http\Middleware\AdminInertia', 'App\Http\Middleware\EnsureAdminUser'])
            ->prefix('admin')
            ->as('admin.')
            ->group(__DIR__ . '/../Routes/routes.php');
    }
}
