<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        }

        if ($request->user() !== null) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $scriptSources = ["'self'"];
        $styleSources = ["'self'", "'unsafe-inline'"];
        $connectSources = ["'self'"];
        $hotFile = public_path('hot');

        if (is_file($hotFile)) {
            $hotUrl = trim((string) file_get_contents($hotFile));
            $parts = parse_url($hotUrl);

            if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
                $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
                $socketScheme = $parts['scheme'] === 'https' ? 'wss' : 'ws';
                $socketOrigin = $socketScheme.'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
                $scriptSources[] = $origin;
                $styleSources[] = $origin;
                $connectSources[] = $origin;
                $connectSources[] = $socketOrigin;
            }
        }

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "img-src 'self' data:",
            "font-src 'self'",
            'style-src '.implode(' ', array_unique($styleSources)),
            'script-src '.implode(' ', array_unique($scriptSources)),
            'connect-src '.implode(' ', array_unique($connectSources)),
        ]).';';
    }
}
