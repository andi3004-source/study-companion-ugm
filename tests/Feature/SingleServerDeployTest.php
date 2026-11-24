<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Single-server Docker deploy (docker-compose.yml, docker/, .env.production.example).
 * Docker itself isn't run here: these check the proxy settings in Laravel and the
 * files' contents.
 */
class SingleServerDeployTest extends TestCase
{
    protected function tearDown(): void
    {
        TrustProxies::flushState();
        parent::tearDown();
    }

    /** Boots the provider again with the given config, then calls a probe route as if through Caddy. */
    private function probe(array $config, array $server = []): array
    {
        config($config);
        (new AppServiceProvider($this->app))->boot();

        Route::get('/_probe', fn (Request $request) => [
            'secure' => $request->secure(),
            'ip' => $request->ip(),
            'url' => url('/halaman'),
        ]);

        return $this->call('GET', '/_probe', [], [], [], $server + [
            'REMOTE_ADDR' => '172.18.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.7',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->assertOk()->json();
    }

    public function test_proxy_is_not_trusted_by_default(): void
    {
        $this->assertNull(config('app.trusted_proxies'));
        $this->assertFalse(config('app.force_https'));

        $result = $this->probe([]);

        $this->assertFalse($result['secure']);
        $this->assertSame('172.18.0.5', $result['ip']);
        $this->assertStringStartsWith('http://', $result['url']);
    }

    public function test_trusted_proxy_headers_and_forced_https(): void
    {
        $result = $this->probe(['app.trusted_proxies' => '*', 'app.force_https' => true]);

        $this->assertTrue($result['secure']);
        $this->assertSame('203.0.113.7', $result['ip']);
        $this->assertStringStartsWith('https://', $result['url']);
    }

    public function test_trusted_proxies_accepts_a_list(): void
    {
        $this->assertSame('203.0.113.7', $this->probe(['app.trusted_proxies' => '10.0.0.1, 172.18.0.5'])['ip']);
        TrustProxies::flushState();
        $this->assertSame('172.18.0.5', $this->probe(['app.trusted_proxies' => '10.0.0.1'])['ip']);
    }

    public function test_health_route_answers(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_sqlite_journal_settings_default_to_sqlite_defaults(): void
    {
        $this->assertNull(config('database.connections.sqlite.journal_mode'));
        $this->assertNull(config('database.connections.sqlite.busy_timeout'));
    }

    public function test_compose_runs_app_worker_and_https_proxy_on_one_volume(): void
    {
        $compose = Yaml::parseFile(base_path('docker-compose.yml'));
        $services = $compose['services'];

        $this->assertEqualsCanonicalizing(['app', 'worker', 'proxy'], array_keys($services));
        $this->assertSame(['php', 'artisan', 'queue:work', '--tries=3', '--sleep=3'], $services['worker']['command']);
        foreach (['app', 'worker'] as $name) {
            $this->assertSame(['data:/data'], $services[$name]['volumes'], $name);
            $this->assertSame('.env', $services[$name]['env_file'], $name);
            $this->assertSame('/data/database/database.sqlite', $services[$name]['environment']['DB_DATABASE'], $name);
            $this->assertSame('Dockerfile', $services[$name]['build']['dockerfile'], $name);
        }
        $this->assertSame('app', $services['app']['environment']['CONTAINER_ROLE']);
        $this->assertSame('worker', $services['worker']['environment']['CONTAINER_ROLE']);
        $this->assertSame('http://127.0.0.1/up', end($services['app']['healthcheck']['test']));

        $this->assertSame('caddy:2-alpine', $services['proxy']['image']);
        $this->assertContains('443:443', $services['proxy']['ports']);
        $this->assertStringContainsString('${DOMAIN:?', $services['proxy']['environment']['DOMAIN']);
        $this->assertArrayNotHasKey('ports', $services['app']);

        $caddy = file_get_contents(base_path('docker/Caddyfile'));
        $this->assertStringContainsString('{$DOMAIN} {', $caddy);
        $this->assertStringContainsString('reverse_proxy app:80', $caddy);
    }

    public function test_entrypoint_migrates_seeds_once_and_caches(): void
    {
        $sh = file_get_contents(base_path('docker/entrypoint.sh'));

        $this->assertStringStartsWith("#!/bin/sh\n", $sh);
        $this->assertStringNotContainsString("\r", $sh);
        foreach ([
            'touch "$DB"',
            'php artisan migrate --force',
            "grep -q '^app:seed-content '",
            'SELECT COUNT(*) FROM mandarin_words',
            'php artisan app:seed-content',
            'php artisan config:cache',
            'php artisan route:cache',
            'php artisan view:cache',
            'exec "$@"',
        ] as $step) {
            $this->assertStringContainsString($step, $sh, $step);
        }
        // Only the app container migrates; the worker waits for it.
        $this->assertMatchesRegularExpression('/if \[ "\$ROLE" = "app" \]; then.*php artisan migrate --force.*else.*until/s', $sh);
        $this->assertArrayHasKey('app:seed-content', $this->app[Kernel::class]->all());
    }

    public function test_dockerfile_builds_assets_and_keeps_secrets_out(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));
        $ignore = file_get_contents(base_path('.dockerignore'));

        $this->assertStringContainsString('RUN npm run build', $dockerfile);
        $this->assertStringContainsString('composer install --no-dev', $dockerfile);
        $this->assertStringContainsString('ln -s /data/storage storage', $dockerfile);
        $this->assertStringNotContainsString('.env', $dockerfile);
        foreach (['.env', '*.sqlite', 'node_modules', 'vendor'] as $pattern) {
            $this->assertMatchesRegularExpression('/^'.preg_quote($pattern, '/').'$/m', $ignore, $pattern);
        }
    }

    public function test_production_env_example_has_the_proxy_settings_and_no_secrets(): void
    {
        $env = file_get_contents(base_path('.env.production.example'));

        foreach (['TRUSTED_PROXIES=172.16.0.0/12,10.0.0.0/8,192.168.0.0/16', 'APP_FORCE_HTTPS=true', 'APP_ENV=staging', 'APP_DEBUG=false', 'DB_JOURNAL_MODE=wal'] as $line) {
            $this->assertMatchesRegularExpression('/^'.preg_quote($line, '/').'$/m', $env, $line);
        }
        foreach (['DOMAIN', 'APP_KEY', 'GEMINI_API_KEY', 'ANTHROPIC_API_KEY', 'LANGFLOW_API_KEY', 'MAIL_PASSWORD'] as $secret) {
            $this->assertMatchesRegularExpression('/^'.$secret.'=$/m', $env, $secret.' must be empty');
        }
        // Values stay plain: docker compose and Laravel would expand ${...} differently.
        $this->assertDoesNotMatchRegularExpression('/^[A-Z_]+=.*\$\{/m', $env);
        $this->assertStringContainsString('.env', file_get_contents(base_path('.gitignore')));
    }

    public function test_readme_documents_single_server_deploy(): void
    {
        $readme = file_get_contents(base_path('README.md'));
        $section = substr($readme, strpos($readme, '## Deploy ke satu server'));
        $section = substr($section, 0, strpos($section, "\n## ", 3));

        foreach ([
            'Server Linux', 'Port 80 dan 443', 'git clone', 'cp .env.production.example .env', 'docker compose up -d --build',
            'docker compose exec app php artisan app:seed-content',
            'git pull', 'VACUUM INTO', 'Batas pengeluaran API', '`REGISTRATION_INVITE_CODE`', '`TRUSTED_PROXIES`',
        ] as $phrase) {
            $this->assertStringContainsString($phrase, $section, $phrase);
        }
        $this->assertDoesNotMatchRegularExpression('/\b(APP_KEY|GEMINI_API_KEY|DB_PASSWORD)=\S/', $section);
    }
}
