<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the response locale from the X-Locale header, falling back to
 * Accept-Language. Only the supported locales (en, am) are honoured.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('gym.locales');

        $locale = $request->header('X-Locale');

        if (! in_array($locale, $supported, true)) {
            $locale = $request->getPreferredLanguage($supported) ?? config('app.locale');
        }

        app()->setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }
}
