<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_standard_security_headers_are_present_on_http_responses(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'microphone=(self), camera=(), geolocation=()');
        $response->assertHeader(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-eval' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; media-src 'self' blob:; connect-src 'self'; font-src 'self'; worker-src 'self'; manifest-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'"
        );
        // HSTS must not be set on insecure HTTP requests
        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
    }

    public function test_strict_transport_security_header_is_set_on_https_requests(): void
    {
        $response = $this->get('https://localhost/');

        $response->assertStatus(200);
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_security_headers_present_on_other_product_routes(): void
    {
        $routes = ['/conversation', '/pronunciation-check', '/buku-saku', '/bantuan'];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $response->assertStatus(200);
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('X-Frame-Options', 'DENY');
            $response->assertHeader('Content-Security-Policy');
            $response->assertHeader('Permissions-Policy', 'microphone=(self), camera=(), geolocation=()');
        }
    }
}
