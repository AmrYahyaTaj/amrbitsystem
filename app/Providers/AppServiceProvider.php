<?php

namespace App\Providers;

use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CompanyContext::class, fn () => new CompanyContext);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-operations', fn (User $user) => app(CompanyContext::class)->role?->managesOperations() ?? false);
        Gate::define('view-directory', fn (User $user) => app(CompanyContext::class)->role?->seesOperations() ?? false);
        RateLimiter::for('login', fn ($request) => [
            Limit::perMinute(5)->by('login:'.hash('sha256', mb_strtolower(trim((string) $request->input('username'))).'|'.$request->ip())),
            Limit::perMinute(20)->by('login-ip:'.$request->ip()),
        ]);
    }
}
