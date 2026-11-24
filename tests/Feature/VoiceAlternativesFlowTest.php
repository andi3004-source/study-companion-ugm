<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** All recognition alternatives are parsed in one request; the client asks only when they really disagree. */
class VoiceAlternativesFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    /** JS source with comments removed, so tests only look at code. */
    private function js(string $file): string
    {
        return preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m'], '', file_get_contents(resource_path('js/'.$file)));
    }

    public function test_endpoint_decides_between_alternatives(): void
    {
        $this->postJson(route('voice.command'), ['transcript' => 'buku saku', 'alternatives' => ['buku saku', 'Buku Saku', 'buka buku saku']])
            ->assertOk()->assertExactJson(['type' => 'navigate', 'target' => 'buku-saku', 'index' => null, 'decision' => 'accept']);

        $this->postJson(route('voice.command'), ['transcript' => 'ucapan', 'alternatives' => ['ucapan', 'bantuan']])
            ->assertOk()
            ->assertJsonPath('decision', 'choose')
            ->assertJsonPath('candidates.0.label', 'ucapan')
            ->assertJsonPath('candidates.1.label', 'bantuan');

        // Without alternatives the answer is exactly as before.
        $this->postJson(route('voice.command'), ['transcript' => 'buku saku'])
            ->assertExactJson(['type' => 'navigate', 'target' => 'buku-saku', 'index' => null]);
    }

    public function test_alternatives_are_validated(): void
    {
        $this->postJson(route('voice.command'), ['transcript' => 'a', 'alternatives' => ['a', 'b', 'c', 'd']])->assertStatus(422);
        $this->postJson(route('voice.command'), ['transcript' => 'a', 'alternatives' => [str_repeat('a', 201)]])->assertStatus(422);
        $this->postJson(route('voice.command'), ['transcript' => 'a', 'alternatives' => []])->assertStatus(422);
    }

    public function test_alternatives_are_not_logged_stored_or_echoed(): void
    {
        Log::spy();
        DB::enableQueryLog();

        $response = $this->postJson(route('voice.command'), [
            'transcript' => 'rahasia-satu-xyz',
            'alternatives' => ['rahasia-satu-xyz', 'rahasia-dua-xyz', 'lanjut'],
        ])->assertOk();

        $this->assertStringNotContainsString('rahasia', $response->getContent());
        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('debug');
        Log::shouldNotHaveReceived('warning');
        foreach (DB::getQueryLog() as $query) {
            $this->assertStringNotContainsString('rahasia', json_encode($query['bindings']));
        }
    }

    public function test_client_parses_all_alternatives_instead_of_comparing_raw_text(): void
    {
        $engine = $this->js('voice/engine.js');

        // The old raw-text comparison is gone.
        $this->assertStringNotContainsString('second !== normalize(heard)', $engine);
        $this->assertStringNotContainsString('alternatives[1].transcript', $engine);
        // One request with every alternative (max 3, 200 characters each).
        $this->assertStringContainsString('.filter(Boolean).slice(0, 3);', $engine);
        $this->assertStringContainsString('await this.parse(heard, texts);', $engine);
    }

    public function test_client_decision_rules(): void
    {
        $engine = $this->js('voice/engine.js');

        // Consistent: act at once. Conflicting: numbered choice ("satu, A, atau dua, B?").
        $this->assertStringContainsString("if (decision === 'choose') return this.clarify(cmd.candidates || []);", $engine);
        $this->assertStringContainsString('Maksud Anda satu, ${candidates[0].label}, atau dua, ${candidates[1].label}? Katakan satu atau dua.', $engine);
        $this->assertStringContainsString('return this.handleParsed(cmd, heard, adapter);', $engine);
        // Only another alternative known: confirm that one.
        $this->assertStringContainsString("if (decision === 'confirm' && texts[heardIndex]) return this.confirmHeard(texts[heardIndex], cmd, adapter);", $engine);
        // Low confidence (< 0.5) only matters for an irreversible action.
        $this->assertStringContainsString('const MIN_CONFIDENCE = 0.5;', $engine);
        $this->assertStringContainsString("if (decision === 'accept' && lowConfidence && this.irreversible(cmd)) return this.confirmHeard(heard, cmd, adapter);", $engine);
        $this->assertStringContainsString('const DANGEROUS = /hapus|reset|selesai dan analisis|keluar/i;', $engine);
        $this->assertMatchesRegularExpression("/irreversible\(cmd\) \{\s*if \(cmd\.type !== 'choose_number'\) return false;.*?DANGEROUS\.test\(/s", $engine);
        $this->assertStringContainsString('Saya dengar: ${heard}. Benar? Katakan ya atau tidak', $engine);
        // Irreversible actions are still confirmed by name even after "ya".
        $this->assertMatchesRegularExpression("/if \(DANGEROUS\.test\(name\)\) \{.*?Yakin\? Katakan ya atau tidak/s", $engine);
        // Dictation, a pending yes/no and a numbered choice skip the decision.
        $this->assertStringContainsString('if (this.dictating || this.pending || this.clarifying || !texts.length) {', $engine);
    }

    public function test_silence_errors_and_cleanup_are_unchanged(): void
    {
        $engine = $this->js('voice/engine.js');

        $this->assertStringContainsString('const SILENCE_MS = 8000;', $engine);
        $this->assertMatchesRegularExpression('/rec\.onerror = \(event\) => \{.*?this\.stop\(MESSAGES\.errors\[event\.error\]/s', $engine);
        $this->assertStringContainsString("window.addEventListener('pagehide', () => VoiceMode.cleanup());", $engine);
        $this->assertMatchesRegularExpression('/rec\.onresult = \(event\) => \{.*?this\.onUtterance\(alternatives\);/s', $engine);
    }
}
