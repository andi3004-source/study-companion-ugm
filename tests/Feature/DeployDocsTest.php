<?php

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Tests\TestCase;

/** README "Deploy ke Laravel Cloud": commands, variable NAMES only, cost warning. */
class DeployDocsTest extends TestCase
{
    private function section(): string
    {
        $readme = file_get_contents(base_path('README.md'));
        $section = substr($readme, strpos($readme, '## Deploy ke Laravel Cloud'));

        return substr($section, 0, strpos($section, "\n## ", 3));
    }

    public function test_lists_build_deploy_worker_and_one_off_commands(): void
    {
        $section = $this->section();

        foreach ([
            'composer install --no-dev --optimize-autoloader', 'npm ci', 'npm run build',
            'php artisan migrate --force', 'php artisan config:cache', 'php artisan route:cache', 'php artisan view:cache',
            'php artisan queue:work --tries=3',
            'php artisan app:seed-content',
        ] as $command) {
            $this->assertStringContainsString($command, $section, $command);
        }
    }

    public function test_lists_variable_names_without_values(): void
    {
        $section = $this->section();

        foreach (['APP_KEY', 'DB_HOST', 'QUEUE_CONNECTION', 'GEMINI_API_KEY', 'LANGFLOW_API_KEY', 'DAILY_MESSAGE_LIMIT'] as $name) {
            $this->assertStringContainsString("`{$name}`", $section, $name);
        }
        $this->assertDoesNotMatchRegularExpression('/\b[A-Z][A-Z0-9_]{2,}=\S/', $section);
    }

    public function test_warns_about_spending_limits_and_quota(): void
    {
        $section = $this->section();

        $this->assertStringContainsString('batas pengeluaran', $section);
        $this->assertStringContainsString('50 pesan', $section);
        $this->assertStringContainsString('translate:meanings', $section);
    }

    public function test_every_documented_command_exists(): void
    {
        $commands = array_keys($this->app[Kernel::class]->all());

        foreach (['app:seed-content', 'translate:meanings', 'langflow:ping', 'queue:work', 'migrate', 'db:seed', 'config:cache', 'route:cache', 'view:cache'] as $command) {
            $this->assertContains($command, $commands, $command);
        }
    }

    public function test_health_route_exists(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_lists_proxy_settings_daily_limit_and_one_off_seeding(): void
    {
        $section = $this->section();

        foreach (['`TRUSTED_PROXIES`', '`APP_FORCE_HTTPS`', '`DAILY_MESSAGE_LIMIT`'] as $name) {
            $this->assertStringContainsString($name, $section, $name);
        }
        $this->assertStringContainsString('php artisan app:seed-content', $section);
        // The old manual instruction is gone now that the proxy is configured from .env.
        $this->assertStringNotContainsString('bootstrap/app.php', $section);
    }
}
