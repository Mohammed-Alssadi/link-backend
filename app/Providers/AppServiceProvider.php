<?php

namespace App\Providers;

use App\Services\SallaService;
use App\Services\ZidService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SallaService::class);
        $this->app->singleton(ZidService::class);
    }

    public function boot(): void
    {
        //
    }
}
