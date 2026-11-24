<?php

namespace Tests\Feature;

use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Mode suara" with natural speech: "Saya dengar", page hints, "mulai",
 * clarify, adapter re-sync, the voicedebug panel. PHPUnit doesn't run the
 * JS, so these check markup and the source of resources/js/voice/*.js.
 */
class VoiceModeNaturalSpeechTest extends TestCase
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
    private function js(string $file): string
    {
        return preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m'], '', file_get_contents(resource_path('js/'.$file)));
    }

    public function test_unknown_and_clarify_say_what_was_heard_and_the_page_hint(): void
    {
        $engine = $this->js('voice/engine.js');

        $this->assertStringContainsString('Di halaman ini Anda bisa berkata: ${adapter?.hint || MESSAGES.defaultHint}. Katakan perintah untuk daftar lengkap', $engine);
        $this->assertMatchesRegularExpression('/notUnderstood\(prefix = MESSAGES\.unknown\) \{.*?Saya dengar: \$\{this\.lastHeard\}.*?return this\.tell\(/s', $engine);
        $this->assertMatchesRegularExpression('/clarify\(candidates\) \{.*?Saya dengar: \$\{this\.lastHeard\}.*?Katakan satu atau dua\..*?\}/s', $engine);
        // tell() writes into the role="status" region and speaks.
        $this->assertMatchesRegularExpression("/async tell\(text\) \{\s*this\.announce\(text\);\s*await this\.say/", $engine);
        $this->assertMatchesRegularExpression("/getElementById\('mode-suara-status'\)/", $engine);
        $this->assertStringContainsString('<p id="mode-suara-status" role="status"', $this->page('/'));
        // Unknown no longer ends at the old dead-end sentence.
        $this->assertStringNotContainsString("'Maaf, perintah tidak dikenali. Katakan perintah untuk daftar perintah.'", $engine);
        $this->assertStringContainsString('default:
                return this.notUnderstood();', $engine);
        // "satu"/"dua" after a clarify picks a candidate.
        $this->assertStringContainsString("if (cmd.type === 'choose_number' && options[cmd.index]) {", $engine);
    }

    public function test_every_page_adapter_has_a_hint_and_help_reads_it(): void
    {
        $adapters = $this->js('voice/adapters.js');
        $engine = $this->js('voice/engine.js');

        foreach (['chat-picker', 'chat'] as $name) {
            $this->assertMatchesRegularExpression("/VoiceMode\\.register\\(\\s*'".$name."',\\s*\\{\\s*hint: '/", $adapters);
        }

        $this->assertStringContainsString("case 'help':
                return this.tell(`\${pageHint(this.adapter())}. \${MESSAGES.general}`);", $engine);
    }

    public function test_start_command_on_scenario_picker(): void
    {
        $engine = $this->js('voice/engine.js');
        $adapters = $this->js('voice/adapters.js');

        $this->assertStringContainsString("case 'start':
                return this.startMain(cmd.target);", $engine);
        $this->assertStringContainsString("querySelectorAll('[data-voice-start]')", $engine);
        $this->assertStringContainsString("return this.offer(starts, 'pilihan');", $engine);
        $this->assertMatchesRegularExpression('/if \(target && this\.routes\[target\]\) \{.*?window\.location\.href = this\.routes\[target\];/s', $engine);
        $picker = $this->page('/conversation');
        $this->assertStringContainsString('data-voice-page="chat-picker"', $picker);
        $this->assertStringContainsString('data-voice-start="Ngobrol Bebas"', $picker);
        $this->assertMatchesRegularExpression('#wire:click="selectScenario\(\'[^\']+\'\)" data-voice-start="[^"]+"#', $picker);
    }

    public function test_adapters_are_registered_for_active_pages(): void
    {
        $adapters = $this->js('voice/adapters.js');
        $views = [
            'chat-picker' => 'conversation/picker',
            'chat' => 'conversation/chat',
            'ucapan' => 'pronunciation-check',
            'bantuan' => 'bantuan',
        ];

        foreach ($views as $name => $view) {
            $this->assertMatchesRegularExpression("/VoiceMode\.register\(\s*'".preg_quote($name, '/')."'/", $adapters, $name);
            $this->assertStringContainsString('data-voice-page="'.$name.'"', file_get_contents(resource_path("views/livewire/{$view}.blade.php")), $name);
        }
    }

    public function test_adapter_resyncs_after_page_load_and_livewire_updates_without_duplicate_listeners(): void
    {
        $engine = $this->js('voice/engine.js');

        $this->assertStringContainsString('this.observer = this.observer || new MutationObserver(() => this.syncAdapter());', $engine);
        $this->assertMatchesRegularExpression('/watch\(\) \{.*?this\.observer\.disconnect\(\);\s*this\.observer\.observe\(main, \{ childList: true, subtree: true \}\);/s', $engine);
        $this->assertMatchesRegularExpression('/cleanup\(\) \{.*?this\.observer\?\.disconnect\(\);/s', $engine);
        $this->assertMatchesRegularExpression('/syncAdapter\(force = false\) \{.*?if \(!force && page === this\.currentPage\) return;.*?onActivate/s', $engine);
        $this->assertMatchesRegularExpression('/this\.watch\(\);\s*this\.syncAdapter\(true\);/', $engine);
        $this->assertDoesNotMatchRegularExpression('/async start\(\) \{.*?this\.listen\(\)/s', $engine);
        $this->assertSame(1, substr_count($engine, "document.addEventListener('livewire:navigated'"));
        // The chat's reply listener is still attached once only.
        $this->assertStringContainsString('if (this.terpasang) return;', $this->js('voice/adapters.js'));
    }

    public function test_debug_panel_is_off_by_default_and_memory_only(): void
    {
        $engine = $this->js('voice/engine.js');

        $this->assertStringContainsString("const DEBUG = new URLSearchParams(window.location.search).get('voicedebug') === '1';", $engine);
        $this->assertMatchesRegularExpression('/debug\(update\) \{\s*if \(!DEBUG\) return;/', $engine);
        foreach (['terdengar', 'alternatif', 'confidence', 'parse:'] as $label) {
            $this->assertStringContainsString($label, $engine);
        }
        $this->assertStringNotContainsString('mode-suara-debug', $this->page('/'));
        $this->assertStringNotContainsString('mode-suara-debug', $this->page('/?voicedebug=1'));
    }

    public function test_transcript_is_never_stored(): void
    {
        $engine = $this->js('voice/engine.js');

        // Only the consent flag, the "aktif" flag and the display settings are written.
        preg_match_all('/(localStorage|sessionStorage)\.setItem\(([^,]+),/', $engine, $m);
        $this->assertEqualsCanonicalizing(['CONSENT_KEY', 'SESSION_KEY', "'tampilan'"], $m[2]);
        $this->assertDoesNotMatchRegularExpression('/indexedDB|document\.cookie/', $engine);
        // The only network call is POST /voice/command.
        $this->assertSame(1, substr_count($engine, 'fetch('));
        $this->assertStringContainsString('await fetch(this.endpoint, {', $engine);
        // The heard text is dropped when the mode stops.
        $this->assertMatchesRegularExpression("/stop\(message = .*?\) \{.*?this\.lastHeard = '';.*?this\.debugState = null;/s", $engine);
        $this->assertDoesNotMatchRegularExpression('/(localStorage|sessionStorage)\.setItem\([^)]*(lastHeard|debugState|transcript)/', $engine);
        $this->assertStringNotContainsString('localStorage', $this->js('voice/adapters.js'));
    }

    public function test_bantuan_lists_natural_commands(): void
    {
        $this->page('/bantuan');

        $this->get('/bantuan')->assertOk()
            ->assertSee('buka ngobrol', false)
            ->assertSee('buka buku saku', false)
            ->assertSee('Saya dengar: …')
            ->assertSee('Belum diuji dengan pengguna tunanetra.');
    }
}
