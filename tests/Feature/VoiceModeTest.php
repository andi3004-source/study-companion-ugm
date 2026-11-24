<?php

namespace Tests\Feature;

use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Mode suara": PHPUnit doesn't run the JS, so these check the layout markup
 * and the source of resources/js/voice/*.js.
 */
class VoiceModeTest extends TestCase
{
    protected function tearDown(): void
    {
        // Livewire remembers "a component was rendered" in static state; without this the next
        // test class in the same process (WelcomePageTest) would get Livewire assets injected.
        Livewire::flushState();
        parent::tearDown();
    }

    private function page(string $url = '/'): string
    {
        return $this->get($url)->assertOk()->getContent();
    }

    /** JS source with comments removed, so tests only look at code. */
    private function js(string $file): string
    {
        return preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m'], '', file_get_contents(resource_path('js/'.$file)));
    }

    public function test_voice_button_is_the_first_focusable_control_after_the_skip_links(): void
    {
        $html = $this->page();
        $button = strpos($html, '<button type="button" id="tombol-mode-suara"');
        $lastSkipEnd = strpos($html, '</a>', strrpos(substr($html, 0, $button), 'class="skip-link"'));
        $between = substr($html, $lastSkipEnd + 4, $button - $lastSkipEnd - 4);

        $this->assertNotFalse($button);
        $this->assertDoesNotMatchRegularExpression('#<(a|button|input|select|textarea|summary)\b#', $between);
        $this->assertLessThan($button, strpos($html, 'Lewati ke konten'));
        $this->assertLessThan(strpos($html, '<header'), $button);
    }

    public function test_global_controls_markup(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('aria-pressed="false" x-bind:aria-pressed="aktif ? \'true\' : \'false\'"', $html);
        $this->assertStringContainsString('min-h-[44px]', $html);
        $this->assertMatchesRegularExpression('#<button type="button" x-show="aktif" x-cloak x-on:click="berhenti\(\)"[^>]*>Berhenti</button>#', $html);
        $this->assertStringContainsString('<p id="mode-suara-status" role="status"', $html);
        $this->assertStringContainsString('Mode suara membutuhkan Chrome atau Edge', $html);
        $this->assertMatchesRegularExpression('#x-data="voiceModeBar\([^"]*voice\\\\?/command#', $html);
    }

    public function test_consent_text_is_shown_before_the_first_microphone_and_only_a_flag_is_stored(): void
    {
        $consent = 'Mode suara memakai pengenalan suara bawaan browser (Chrome atau Edge). Suaramu dikirim ke penyedia browser untuk diubah menjadi teks. Aplikasi ini tidak menyimpan suara.';
        $this->assertStringContainsString($consent, $this->page());

        $engine = $this->js('voice/engine.js');
        $this->assertStringContainsString($consent, $engine);
        $this->assertStringContainsString("localStorage.setItem(CONSENT_KEY, '1')", $engine);
        $this->assertStringContainsString('if (!VoiceMode.consented()) {', $engine);
    }

    public function test_mutually_exclusive_with_screen_reader_mode(): void
    {
        $engine = $this->js('voice/engine.js');
        $app = $this->js('app.js');

        $this->assertStringContainsString("document.documentElement.classList.remove('mode-pembaca-layar')", $engine);
        $this->assertStringContainsString("new CustomEvent('mode-pembaca-layar-mati')", $engine);
        $this->assertStringContainsString('Mode pembaca layar dimatikan karena Mode suara aktif.', $engine);
        $this->assertStringContainsString("window.addEventListener('mode-pembaca-layar-aktif'", $engine);
        $this->assertStringContainsString('Mode suara dimatikan karena Mode pembaca layar aktif.', $engine);

        $this->assertStringContainsString("if (this.pembacaLayar) window.dispatchEvent(new CustomEvent('mode-pembaca-layar-aktif'));", $app);
        $this->assertStringContainsString("window.addEventListener('mode-pembaca-layar-mati', () => (this.pembacaLayar = false));", $app);
    }

    public function test_feature_detection(): void
    {
        $engine = $this->js('voice/engine.js');

        $this->assertStringContainsString('const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;', $engine);
        $this->assertStringContainsString("return !!Recognition && 'speechSynthesis' in window;", $engine);
        $this->assertStringContainsString("unsupported: 'Mode suara membutuhkan Chrome atau Edge'", $engine);
    }

    public function test_recognition_settings_turn_taking_silence_and_confirmation(): void
    {
        $engine = $this->js('voice/engine.js');

        $this->assertStringContainsString("rec.lang = 'id-ID';", $engine);
        $this->assertStringContainsString('rec.maxAlternatives = 3;', $engine);
        $this->assertStringContainsString('rec.interimResults = false;', $engine);
        // Never listen while speaking; speaking closes the microphone; a beep before the microphone opens.
        $this->assertStringContainsString('if (!this.active || this.speaking || this.listening) return;', $engine);
        $this->assertMatchesRegularExpression('/say\(segments\) \{.*?this\.stopListening\(\);/s', $engine);
        $this->assertMatchesRegularExpression('/async listen\(\) \{.*?await this\.beep\(\);.*?rec\.start\(\);/s', $engine);
        $this->assertStringContainsString('createOscillator()', $engine);
        // Silence: 8 seconds, two reminders, then stop and say the keyboard still works.
        $this->assertStringContainsString('const SILENCE_MS = 8000;', $engine);
        $this->assertStringContainsString('const MAX_REMINDERS = 2;', $engine);
        $this->assertStringContainsString('Keyboard tetap bisa dipakai.', $engine);
        // Confirmation only below 0.5 on an irreversible action (or when only another alternative is known).
        $this->assertStringContainsString('const MIN_CONFIDENCE = 0.5;', $engine);
        $this->assertStringContainsString('Saya dengar: ${heard}. Benar? Katakan ya atau tidak', $engine);
        // zh-CN voice warning, language per segment.
        $this->assertStringContainsString("u.lang = segment.lang || 'id-ID';", $engine);
        $this->assertStringContainsString('Suara Mandarin belum terpasang', $engine);
    }

    public function test_cleanup_on_pagehide_stop_and_errors_with_friendly_messages(): void
    {
        $engine = $this->js('voice/engine.js');

        $this->assertStringContainsString("window.addEventListener('pagehide', () => VoiceMode.cleanup());", $engine);
        $this->assertMatchesRegularExpression('/cleanup\(\) \{.*?this\.recognition\.abort\(\);.*?window\.speechSynthesis\.cancel\(\);.*?this\.audioCtx\.close\(\)/s', $engine);
        $this->assertMatchesRegularExpression('/stop\(message = .*?\) \{.*?this\.cleanup\(\);/s', $engine);
        $this->assertMatchesRegularExpression('/rec\.onerror = \(event\) => \{.*?this\.stop\(MESSAGES\.errors\[event\.error\]/s', $engine);
        // Each recognition error has a friendly Indonesian message (key quoted or not).
        foreach (['not-allowed', 'no-speech', 'network', 'aborted', 'audio-capture'] as $code) {
            $this->assertMatchesRegularExpression("/errors: \{.*?'?{$code}'?: '[^']+'/s", $engine, $code);
        }
        $this->assertStringContainsString("document.getElementById('mode-suara-status')", $engine);
    }

    public function test_resume_after_page_load_uses_session_storage_and_asks_for_a_tap(): void
    {
        $engine = $this->js('voice/engine.js');

        $this->assertStringContainsString("sessionStorage.setItem(SESSION_KEY, 'aktif')", $engine);
        $this->assertStringContainsString('Mode suara aktif. Ketuk atau tekan Enter pada tombol Mode suara untuk melanjutkan mikrofon', $engine);
        $this->assertStringContainsString("document.getElementById('tombol-mode-suara')?.focus();", $engine);
    }

    public function test_no_audio_image_or_transcript_is_stored_in_the_browser(): void
    {
        foreach (['voice/engine.js', 'voice/adapters.js'] as $file) {
            $js = $this->js($file);

            $this->assertStringNotContainsStringIgnoringCase('indexedDB', $js, $file);
            $this->assertStringNotContainsString('MediaRecorder', $js, $file);
            $this->assertStringNotContainsString('getUserMedia', $js, $file);
            preg_match_all('/(localStorage|sessionStorage)\.setItem\(([^,]+),/', $js, $m);
            foreach ($m[2] as $key) {
                $this->assertContains(trim($key), ['CONSENT_KEY', 'SESSION_KEY', "'tampilan'"], "{$file}: {$key}");
            }
        }

        // The only request carries the transcript text (and its alternatives), nothing else.
        $engine = $this->js('voice/engine.js');
        $this->assertSame(1, substr_count($engine, 'fetch('));
        $this->assertStringContainsString('const body = { transcript: String(raw).slice(0, 200) };', $engine);
        $this->assertStringContainsString('if (alternatives) body.alternatives = alternatives;', $engine);
        $this->assertStringContainsString('body: JSON.stringify(body),', $engine);
    }

    public function test_no_global_single_key_shortcuts(): void
    {
        foreach (['voice/engine.js', 'voice/adapters.js'] as $file) {
            $js = $this->js($file);
            $this->assertDoesNotMatchRegularExpression('/(document|window)\.addEventListener\(\s*[\'"]key(down|up|press)/', $js, $file);
            $this->assertStringNotContainsString('onkeydown', $js, $file);
        }
    }

    public function test_irreversible_actions_ask_first_and_hidden_or_disabled_controls_are_skipped(): void
    {
        $engine = $this->js('voice/engine.js');

        $this->assertStringContainsString('const DANGEROUS = /hapus|reset|selesai dan analisis|keluar/i;', $engine);
        $this->assertStringContainsString("return this.tell('Yakin? Katakan ya atau tidak');", $engine);
        $this->assertStringContainsString("if (el.getAttribute('aria-disabled') === 'true') return false;", $engine);
        $this->assertStringContainsString("el.closest('[disabled], template, [aria-hidden=\"true\"], [inert]')", $engine);
        $this->assertStringContainsString('const GROUP_SIZE = 6;', $engine);
    }

    public function test_every_adapter_is_registered_and_its_page_is_marked(): void
    {
        $adapters = $this->js('voice/adapters.js');
        preg_match_all("/VoiceMode\.register\(\s*'([a-z-]+)'/", $adapters, $m);

        foreach (['chat', 'chat-picker', 'ucapan', 'bantuan'] as $name) {
            $this->assertContains($name, $m[1]);
        }

        $views = collect([
            resource_path('views/livewire/conversation/chat.blade.php'),
            resource_path('views/livewire/conversation/picker.blade.php'),
            resource_path('views/livewire/pronunciation-check.blade.php'),
            resource_path('views/livewire/bantuan.blade.php'),
        ])->map(fn ($p) => file_get_contents($p))->implode("\n");
        foreach (['chat', 'chat-picker', 'ucapan', 'bantuan'] as $name) {
            $this->assertStringContainsString('data-voice-page="'.$name.'"', $views, $name);
        }
    }

    public function test_voice_route_allowlist_contains_only_active_pages(): void
    {
        $html = $this->page();
        preg_match('#x-data="(voiceModeBar\([^"]*)"#', $html, $m);
        $bar = $m[1] ?? '';

        foreach (['home', 'conversation', 'pronunciation-check', 'buku-saku', 'bantuan'] as $route) {
            $this->assertStringContainsString($route, $bar);
        }
        foreach (['kamus', 'ujian', 'game', 'cards', 'scan-benda', 'dashboard'] as $route) {
            $this->assertStringNotContainsString($route, $bar);
        }
    }
}
