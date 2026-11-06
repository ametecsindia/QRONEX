<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser hardening for every web page. Scripts/styles are self-hosted except Razorpay Checkout.
 * ponytail: 'unsafe-inline' (small inline page scripts) and 'unsafe-eval' (Alpine expressions) stay until those move to files / Alpine CSP build.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $rzp = 'https://*.razorpay.com';
        $h = $response->headers;

        $h->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' $rzp",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https://*.tile.openstreetmap.org $rzp",
            "connect-src 'self' $rzp",
            "frame-src $rzp",
            "form-action 'self' $rzp",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "object-src 'none'",
        ]));
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // The portal itself never needs camera/mic; geolocation only for nothing on web (the app uses native GPS).
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self "https://checkout.razorpay.com")');
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
