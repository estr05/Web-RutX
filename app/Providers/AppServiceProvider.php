<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Auth\GenericUser;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Auth::viaRequest('jwt-session', function (Request $request) {
            return session()->has('api_token') ? new GenericUser(['id' => 1]) : null;
        });

        Gate::before(function ($user, $ability) {
            return in_array($ability, session('permissions', [])) ? true : null;
        });
    }
}
