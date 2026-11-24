<?php

namespace Tests\Feature;

use Livewire\Livewire;
use Tests\TestCase;

/**
 * Feature-specific voice commands (resources/js/voice/adapters.js). PHPUnit
 * doesn't run the JS, so these check the page markers the adapters read and
 * the adapter source: every command presses an existing control or fills an
 * existing field, so each feature's own rules (and server validation) apply.
 */
class VoiceFeatureCommandsTest extends TestCase
{
    protected function tearDown(): void
    {
        Livewire::flushState();
        parent::tearDown();
    }

    private function page(string $url): string
    {
        return $this->get($url)->assertOk()->getContent();
    }

    /** JS source with comments removed, so tests only look at code. */
    private function js(string $file = 'voice/adapters.js'): string
    {
        return preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m'], '', file_get_contents(resource_path('js/'.$file)));
    }

    /** The source of one registered adapter, up to the next registration. */
    private function adapter(string $name): string
    {
        $js = $this->js();
        $start = strpos($js, "VoiceMode.register(\n    '{$name}'") ?: strpos($js, "VoiceMode.register('{$name}'");
        $this->assertNotFalse($start, "adapter {$name}");
        $next = strpos($js, 'VoiceMode.register(', $start + 20);

        return substr($js, $start, $next === false ? null : $next - $start);
    }

    public function test_engine_can_capture_one_answer_in_another_language(): void
    {
        $engine = $this->js('voice/engine.js');

        $this->assertStringContainsString("rec.lang = 'id-ID';", $engine);
        $this->assertStringContainsString('if (this.capture?.lang) rec.lang = this.capture.lang;', $engine);
        $this->assertMatchesRegularExpression('/captureNext\(lang, onText\) \{.*?normalize\(text\) === \'batal\'/s', $engine);
        // The captured answer bypasses command parsing and is used once.
        $this->assertMatchesRegularExpression('/if \(this\.capture\) \{\s*const \{ onText \} = this\.capture;\s*this\.capture = null;/', $engine);
        $this->assertMatchesRegularExpression('/stop\(message = .*?\) \{.*?this\.capture = null;/s', $engine);
    }

    public function test_shared_helpers_read_hanzi_in_mandarin_and_numbers_in_words(): void
    {
        $js = $this->js();

        $this->assertStringContainsString("lang: /\\p{Script=Han}/u.test(part) ? 'zh-CN' : 'id-ID'", $js);
        $this->assertStringContainsString("} else if (word === 'belas') {", $js);
        $this->assertStringContainsString("} else if (word === 'puluh') {", $js);
        // Emoji and arrows hidden from screen readers are not spoken either.
        $this->assertStringContainsString('clone.querySelectorAll(\'[aria-hidden="true"]\').forEach((n) => n.remove());', $js);
    }

    public function test_ucapan_records_through_the_existing_button_and_reads_the_result(): void
    {
        $html = $this->page('/pronunciation-check');
        $ucapan = $this->adapter('ucapan');

        $this->assertStringContainsString('data-voice-page="ucapan"', $html);
        $view = file_get_contents(resource_path('views/livewire/pronunciation-check.blade.php'));
        $this->assertStringContainsString('data-saran data-lang-code="{{ $entry->lang_code }}"', $view);
        $this->assertStringContainsString('<div data-kata-terpilih', $view);

        // Recording presses "Rekam suara", then "Berhenti merekam"; the server judges the result.
        $this->assertMatchesRegularExpression('/const button = buttonNamed\(\/rekam suara\/i\);.*?button\.click\(\);\s*await wait\(RECORD_MS\);\s*buttonNamed\(\/berhenti merekam\/i\)\?\.click\(\);/s', $ucapan);
        $this->assertStringContainsString("await engine.tell('Setelah bunyi, ucapkan katanya.", $ucapan);
        $this->assertStringContainsString('await engine.beep();', $ucapan);
        // The adapter never opens the microphone itself.
        $this->assertStringNotContainsString('getUserMedia', $ucapan);
        $this->assertStringNotContainsString('MediaRecorder', $ucapan);
        foreach (['fill(field, cari[1]', "choose(document.getElementById('ucapan-bahasa')", 'buttonNamed(/coba lagi/i)', 'buttonNamed(/ganti kata|cari kata lain/i)'] as $part) {
            $this->assertStringContainsString($part, $ucapan, $part);
        }
    }

    public function test_bantuan_reads_sections_by_number(): void
    {
        $html = $this->page('/bantuan');
        $bantuan = $this->adapter('bantuan');

        $this->assertStringContainsString('data-voice-page="bantuan"', $html);
        $this->assertStringContainsString("root().querySelectorAll('section')", $bantuan);
        $this->assertStringContainsString('Katakan nomor bagian untuk mendengarnya.', $bantuan);
        foreach (['"rekam"', '"baca halaman"', '"berhenti"', 'buku saku'] as $phrase) {
            $this->assertStringContainsString($phrase, $html, $phrase);
        }
    }

    public function test_active_livewire_pages_have_voice_adapters(): void
    {
        foreach (['conversation/picker', 'conversation/chat', 'pronunciation-check', 'bantuan'] as $view) {
            $path = resource_path("views/livewire/{$view}.blade.php");
            preg_match('/data-voice-page="([a-z-]+)"/', file_get_contents($path), $m);
            $this->assertNotEmpty($m, basename($path).' has no data-voice-page');
            $this->assertMatchesRegularExpression("/VoiceMode\\.register\\(\\s*'".preg_quote($m[1], '/')."'/", $this->js(), $m[1]);
        }
    }
}
