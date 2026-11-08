<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
  public function boot(): void
    {
        // Force HTTPS generation for all routes in production except local dev server hosts
        if ($this->app->environment('local') === false) {
            $host = request()->getHost();
            if (!in_array($host, ['localhost', '127.0.0.1', '10.0.2.2']) && !str_starts_with($host, '192.168.') && !str_starts_with($host, '10.')) {
                URL::forceScheme('https');
            }
        }
    }
}
