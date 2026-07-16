<?php

namespace App\Providers;

use App\Models\Aviso;
use App\Observers\AvisoObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (env('FORCE_HTTPS') == 'true') {
            URL::forceScheme('https');
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Aviso::observe(AvisoObserver::class);
    }
}
// app/Providers/AppServiceProvider.php