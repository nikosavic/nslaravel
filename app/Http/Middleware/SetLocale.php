<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $supported = ['en', 'tr'];

        // 1) URL locale has priority (/{locale}/...)
        $routeLocale = $request->route('locale');
        if (is_string($routeLocale) && in_array($routeLocale, $supported, true)) {
            app()->setLocale($routeLocale);
            session(['locale' => $routeLocale]); // keep in sync
            return $next($request);
        }

        // 2) Session fallback
        $sessionLocale = session('locale');
        if (is_string($sessionLocale) && in_array($sessionLocale, $supported, true)) {
            app()->setLocale($sessionLocale);
            return $next($request);
        }

        // 3) Default fallback
        app()->setLocale('en');
        session(['locale' => 'en']);

        return $next($request);
    }
}