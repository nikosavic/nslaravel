<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = session('locale');

        if (in_array($locale, ['en', 'tr'])) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}