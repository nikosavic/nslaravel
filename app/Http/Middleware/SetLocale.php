<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        // 1) Explicit locale from /lang/{locale} route (stored in session)
        $locale = session('locale');

        // 2) If not set, use browser language (Accept-Language)
        if (!$locale) {
            $header = strtolower((string) $request->header('accept-language', ''));

            // very simple: if Turkish is anywhere in the header, use tr
            $locale = str_contains($header, 'tr') ? 'tr' : 'en';

            // store it so next request is consistent
            session(['locale' => $locale]);
        }

        // 3) Apply
        if (!in_array($locale, ['en', 'tr'], true)) {
            $locale = 'en';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}