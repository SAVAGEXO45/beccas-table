<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Gate, RateLimiter, URL};
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('manage-restaurant', fn (User $user) => $user->is_admin && !$user->is_disabled);
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(20)->by($request->ip()),
            Limit::perMinute(5)->by($request->ip().'|'.hash('sha256', strtolower($request->string('email')->toString()))),
        ]);

        if ($this->app->environment('production') && str_starts_with(config('app.url'), 'https://')) {
            URL::useOrigin(config('app.url'));
            URL::forceScheme('https');
        }
    }
}

