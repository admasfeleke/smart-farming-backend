<?php

namespace App\Providers;

use App\Models\DiseaseReport;
use App\Observers\DiseaseReportObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        DiseaseReport::observe(DiseaseReportObserver::class);

        RateLimiter::for('api-auth', function (Request $request): array {
            return [
                Limit::perMinute(30)->by($request->ip()),
            ];
        });

        RateLimiter::for('api-write', function (Request $request): array {
            $key = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();

            return [
                Limit::perMinute(60)->by($key),
            ];
        });
    }
}

