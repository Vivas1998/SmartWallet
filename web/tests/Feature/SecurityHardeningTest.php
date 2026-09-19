<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_responses_include_defensive_security_headers(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'same-origin')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');

        $this->assertStringContainsString("default-src 'self'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("frame-ancestors 'none'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertSame('camera=(), microphone=(), geolocation=(), payment=(), usb=()', $response->headers->get('Permissions-Policy'));
    }

    public function test_authenticated_financial_pages_are_not_cached(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
    }

    public function test_vite_hot_styles_are_allowed_by_content_security_policy(): void
    {
        $hotFile = public_path('hot');
        $originalContents = is_file($hotFile) ? file_get_contents($hotFile) : false;

        file_put_contents($hotFile, 'http://127.0.0.1:5174');

        try {
            $response = $this->get(route('login'));
            $policy = (string) $response->headers->get('Content-Security-Policy');

            $this->assertStringContainsString(
                "style-src 'self' 'unsafe-inline' http://127.0.0.1:5174",
                $policy,
            );
            $this->assertStringContainsString(
                "script-src 'self' http://127.0.0.1:5174",
                $policy,
            );
        } finally {
            if ($originalContents === false) {
                @unlink($hotFile);
            } else {
                file_put_contents($hotFile, $originalContents);
            }
        }
    }

    public function test_https_responses_enable_transport_security(): void
    {
        $this->get('https://localhost/acceder')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_all_private_application_routes_require_authentication(): void
    {
        $privateRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => $route->uri() === '/'
                || str_starts_with($route->uri(), 'perfil')
                || str_starts_with($route->uri(), 'proyectos')
                || $route->uri() === 'salir');

        $this->assertNotEmpty($privateRoutes);
        foreach ($privateRoutes as $route) {
            $this->assertContains('auth', $route->gatherMiddleware(), $route->uri().' debe exigir autenticación.');
        }
    }

    public function test_unused_private_storage_routes_are_not_exposed(): void
    {
        $this->assertFalse(Route::has('storage.local'));
        $this->assertFalse(Route::has('storage.local.upload'));
    }

    public function test_repeated_login_attempts_are_temporarily_limited(): void
    {
        RateLimiter::clear('unused');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('login'))->post(route('login'), [
                'email' => 'nadie@example.test',
                'password' => 'una clave incorrecta',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('login'), [
            'email' => 'nadie@example.test',
            'password' => 'una clave incorrecta',
        ])->assertTooManyRequests();
    }
}
