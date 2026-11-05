<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddPrivateResponseHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set(
            'X-Frame-Options',
            $request->routeIs('ppdb.documents.preview') ? 'SAMEORIGIN' : 'DENY'
        );
        if ($request->routeIs('ppdb.documents.preview')) {
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        }
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
