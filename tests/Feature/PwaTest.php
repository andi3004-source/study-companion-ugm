<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Symfony\Component\Mime\MimeTypes;
use Tests\TestCase;

/** Kontrak PWA: manifest, layout, beranda pasang, Bantuan #pasang, service worker. */
class PwaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test-beranda', fn () => view('layouts.study', [
            'title' => 'Tuturnusa',
            'slot' => new HtmlString(view('home')->render()),
        ]))->name('home');

        Route::get('/_test-buku-saku', fn () => '#')->name('buku-saku');

        Route::getRoutes()->refreshNameLookups();
    }

    protected function onboardedUser(): User
    {
        return User::factory()->create([
            'start_date' => now()->subDays(2)->toDateString(),
            'track' => 'both',
            'onboarded' => true,
        ]);
    }

    public function test_manifest_is_valid_json_with_required_pwa_fields(): void
    {
        $path = public_path('manifest.json');
        $this->assertFileExists($path);

        $manifest = json_decode(file_get_contents($path), true);
        $this->assertIsArray($manifest);

        foreach (['name', 'short_name', 'start_url', 'scope', 'display', 'background_color', 'theme_color', 'lang'] as $key) {
            $this->assertArrayHasKey($key, $manifest, "manifest harus punya {$key}");
        }

        $this->assertSame('Tuturnusa', $manifest['name']);
        $this->assertSame('Tuturnusa', $manifest['short_name']);
        $this->assertSame('id', $manifest['lang']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('#FFFFFF', $manifest['background_color']);
        $this->assertSame('#14294D', $manifest['theme_color']);
        $this->assertNotEmpty($manifest['icons']);

        $sizes = array_column($manifest['icons'], 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);

        $maskable = collect($manifest['icons'])->first(function ($icon) {
            $purpose = $icon['purpose'] ?? [];
            $purposes = is_array($purpose) ? $purpose : [$purpose];

            return in_array('maskable', $purposes, true) && ($icon['sizes'] ?? '') === '512x512';
        });
        $this->assertNotNull($maskable);
    }

    public function test_manifest_file_is_web_manifest_mime_type(): void
    {
        $path = public_path('manifest.json');
        $this->assertFileExists($path);
        $mime = (new MimeTypes)->guessMimeType($path);
        $this->assertContains($mime, ['application/manifest+json', 'application/json', 'text/plain']);
    }

    public function test_study_layout_includes_manifest_and_apple_touch_icon(): void
    {
        $html = $this->actingAs($this->onboardedUser())->get('/bantuan')->assertOk()->getContent();
        $head = substr($html, 0, strpos($html, '</head>'));

        $this->assertStringContainsString('rel="manifest"', $head);
        $this->assertStringContainsString('manifest.json', $head);
        $this->assertStringContainsString('name="theme-color" content="#14294D"', $head);
        $this->assertStringContainsString('rel="apple-touch-icon"', $head);
    }

    public function test_beranda_has_pasang_section_with_hidden_install_button_and_help_link(): void
    {
        $html = $this->get('/_test-beranda')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<section[^>]*aria-labelledby="pasang-aplikasi-judul"/', $html);
        $this->assertStringContainsString('id="pasang-aplikasi-judul"', $html);
        $this->assertStringContainsString('Pasang Tuturnusa di perangkat ini', $html);

        $this->assertMatchesRegularExpression(
            '/<button[^>]*type="button"[^>]*hidden[^>]*>[\s\S]*?Pasang aplikasi/',
            $html
        );
        $this->assertStringContainsString(route('bantuan').'#pasang', $html);
        $this->assertStringContainsString('Cara memasang', $html);
    }

    public function test_bantuan_has_pasang_section_with_platform_steps(): void
    {
        $html = $this->actingAs($this->onboardedUser())->get('/bantuan')->assertOk()->getContent();

        $this->assertStringContainsString('id="pasang"', $html);
        $this->assertStringContainsString('Tambahkan ke Layar Utama', $html);
    }

    public function test_service_worker_template_has_build_placeholders(): void
    {
        $template = file_get_contents(resource_path('js/sw.template.js'));

        $this->assertStringContainsString('__CACHE_VERSION__', $template);
        $this->assertStringContainsString('__PRECACHE_URLS__', $template);
        $this->assertStringContainsString('/manifest.json', $template);
        $this->assertStringContainsString('parseByteRange', $template);
        $this->assertStringContainsString("spec.startsWith('-')", $template);
        $this->assertStringContainsString('416', $template);
        $this->assertStringContainsString('/audio/buku-saku/', $template);
    }

    public function test_service_worker_template_uses_network_first_for_navigation(): void
    {
        $template = file_get_contents(resource_path('js/sw.template.js'));

        $this->assertStringContainsString('networkFirstNavigation', $template);
        $this->assertStringContainsString('NAV_TIMEOUT_MS', $template);
        $this->assertStringContainsString('isCachedNavigation', $template);
        $this->assertStringNotContainsString('bukuPageStrategy', $template);
        $this->assertStringNotContainsString('stale', $template);
        $this->assertStringContainsString('buildAssetStrategy', $template);
        $this->assertStringContainsString('isLegacyAudioCacheName', $template);
    }

    public function test_built_service_worker_exists_after_build_and_replaces_placeholders(): void
    {
        $path = public_path('sw.js');
        $this->assertFileExists($path, 'public/sw.js harus ada setelah npm run build (lihat scripts/ci.sh)');

        $sw = file_get_contents($path);
        $this->assertStringNotContainsString('__CACHE_VERSION__', $sw);
        $this->assertStringNotContainsString('__PRECACHE_URLS__', $sw);
        $this->assertMatchesRegularExpression("/const CACHE_VERSION = '[0-9a-f]{12}';/", $sw);
        $this->assertStringContainsString('const PRECACHE_URLS = [', $sw);
        $this->assertStringContainsString('addEventListener', $sw);
    }

    public function test_pwa_js_module_is_bundled_via_app_entry(): void
    {
        $appJs = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('./pwa', $appJs);

        $pwaJs = file_get_contents(resource_path('js/pwa.js'));
        $this->assertStringContainsString('beforeinstallprompt', $pwaJs);
        $this->assertStringContainsString('Tuturnusa sedang dipasang', $pwaJs);
        $this->assertStringContainsString('Pemasangan dibatalkan', $pwaJs);
        $this->assertStringContainsString('display-mode: standalone', $pwaJs);
        $this->assertStringContainsString('serviceWorker', $pwaJs);
    }

    public function test_offline_fallback_page_exists_in_indonesian(): void
    {
        $offlineHtml = public_path('offline.html');
        $offlineBlade = resource_path('views/offline.blade.php');

        $this->assertTrue(
            file_exists($offlineHtml) || file_exists($offlineBlade),
            'Harus ada offline.html atau offline.blade.php'
        );

        $text = file_exists($offlineHtml)
            ? file_get_contents($offlineHtml)
            : file_get_contents($offlineBlade);

        $this->assertMatchesRegularExpression('/offline|tanpa.?internet|tidak ada koneksi/i', $text);
        $this->assertStringContainsString('lang="id"', $text);
    }

    public function test_pwa_icons_exist_at_required_sizes(): void
    {
        foreach (['icon-192.png', 'icon-512.png', 'icon-maskable-512.png'] as $file) {
            $this->assertFileExists(public_path("icons/{$file}"), "Ikon {$file} harus ada");
        }
    }
}
