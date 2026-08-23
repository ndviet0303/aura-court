<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $acceptHeader = $request->header('Accept-Language');
        $locale = $request->header('X-Locale')
            ?: $request->get('lang')
            ?: ($acceptHeader ? $request->getPreferredLanguage(['vi', 'en']) : null)
            ?: config('app.locale', 'vi')
            ?: 'vi';

        if (is_string($locale) && str_starts_with(strtolower($locale), 'en')) {
            App::setLocale('en');
        } else {
            App::setLocale('vi');
        }

        return $next($request);
    }
}
