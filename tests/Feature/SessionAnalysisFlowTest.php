<?php

namespace Tests\Feature;

use App\Contracts\AiClient;
use App\Contracts\SessionAnalyzer;
use App\Jobs\AnalyzeSessionJob;
use App\Livewire\Conversation\Chat;
use App\Models\ConversationApiCall;
use App\Models\ConversationSession;
use App\Models\ConversationTurn;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class SessionAnalysisFlowTest extends TestCase
{
    use RefreshDatabase;

    private const ANALYSIS = [
        'items' => [
            ['kategori' => 'shi_dengan_kata_sifat', 'contoh' => '我是高兴', 'koreksi' => '我很高兴', 'penjelasan' => 'Kata sifat tidak memakai 是.'],
            ['kategori' => 'bu_atau_mei', 'contoh' => '我不有钱', 'koreksi' => '我没有钱', 'penjelasan' => '有 diingkari dengan 没.'],
        ],
        'catatan' => 'Dua kesalahan dasar.',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.langflow' => ['url' => null, 'flow_id' => null, 'api_key' => null, 'timeout' => 120]]);
    }

    /** A fake SessionAnalyzer: returns $result, or throws when $result is null. */
    private function fakeAnalyzer(?array $result = self::ANALYSIS, bool $configured = true): object
    {
        $analyzer = new class($result, $configured) implements SessionAnalyzer
        {
            public array $transcripts = [];

            public function __construct(private ?array $result, private bool $configured) {}

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function analyze(string $transcript, ?int $sessionId = null): array
            {
                $this->transcripts[] = $transcript;

                return $this->result ?? throw new RuntimeException('simulated analyzer failure');
            }
        };

        $this->app->instance(SessionAnalyzer::class, $analyzer);

        return $analyzer;
    }

    /** Fake AiClient so endSession's existing feedback call stays offline. */
    private function fakeAiClient(bool $configured = true): void
    {
        $this->app->instance(AiClient::class, new class($configured) implements AiClient
        {
            public function __construct(private bool $configured) {}

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function sendMessage(string $model, string $system, array $messages, int $maxTokens = 200): array
            {
                return ['text' => 'Catatan feedback.', 'tokens_in' => 1, 'tokens_out' => 1];
            }
        });
    }

    private function mandarinSession(bool $withUserTurn = true, string $lang = 'mandarin', ?User $user = null): ConversationSession
    {
        $user ??= User::factory()->create(['start_date' => now()->toDateString(), 'track' => 'both', 'onboarded' => true]);
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => $lang,
            'scenario_key' => $lang === 'mandarin' ? 'restaurant_ordering' : 'job_interview', 'started_at' => now(),
        ]);

        session()->push('ngobrol.sesi', $session->id);

        if ($withUserTurn) {
            ConversationTurn::create(['session_id' => $session->id, 'role' => 'user', 'content' => '我是高兴', 'input_mode' => 'text']);
            ConversationTurn::create(['session_id' => $session->id, 'role' => 'assistant', 'content' => '你为什么高兴？']);
            ConversationTurn::create(['session_id' => $session->id, 'role' => 'user', 'content' => '我不有钱', 'input_mode' => 'voice']);
        }

        return $session;
    }

    // --- AnalyzeSessionJob ---

    public function test_job_stores_the_analysis_without_usage_and_logs_tokens(): void
    {
        $analyzer = $this->fakeAnalyzer(self::ANALYSIS + ['usage' => ['tokens_in' => 300, 'tokens_out' => 80]]);
        $session = $this->mandarinSession();

        AnalyzeSessionJob::dispatchSync($session->id);

        $session->refresh();
        $this->assertSame(self::ANALYSIS, $session->error_analysis);
        $this->assertArrayNotHasKey('usage', $session->error_analysis);
        $this->assertStringNotContainsString('usage', $session->getRawOriginal('error_analysis'));
        $this->assertNotNull($session->analyzed_at);
        $this->assertStringContainsString('User (suara): 我不有钱', $analyzer->transcripts[0]);

        $this->assertDatabaseHas('conversation_api_calls', ['session_id' => $session->id, 'tokens_in' => 300, 'tokens_out' => 80]);
    }

    public function test_job_stores_no_mistakes_as_empty_items_not_null(): void
    {
        $this->fakeAnalyzer(['items' => [], 'catatan' => 'Tidak ada kesalahan.']);
        $session = $this->mandarinSession();

        AnalyzeSessionJob::dispatchSync($session->id);

        $this->assertSame(['items' => [], 'catatan' => 'Tidak ada kesalahan.'], $session->fresh()->error_analysis);
        $this->assertSame(0, ConversationApiCall::count()); // no usage reported, nothing logged
    }

    public function test_job_does_not_throw_when_the_analyzer_fails_and_records_the_failure(): void
    {
        Exceptions::fake();
        $this->fakeAnalyzer(null);
        $session = $this->mandarinSession();

        AnalyzeSessionJob::dispatchSync($session->id);

        $session->refresh();
        $this->assertNotNull($session->analyzed_at);
        $this->assertNull($session->error_analysis);
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_job_skips_an_already_analyzed_session(): void
    {
        $analyzer = $this->fakeAnalyzer();
        $session = $this->mandarinSession();
        $session->update(['analyzed_at' => now(), 'error_analysis' => ['items' => [], 'catatan' => null]]);

        AnalyzeSessionJob::dispatchSync($session->id);

        $this->assertSame([], $analyzer->transcripts);
        $this->assertSame([], $session->fresh()->error_analysis['items']);
    }

    public function test_job_skips_english_sessions_and_sessions_without_user_turns(): void
    {
        $analyzer = $this->fakeAnalyzer();
        $english = $this->mandarinSession(lang: 'english');
        $empty = $this->mandarinSession(withUserTurn: false);

        AnalyzeSessionJob::dispatchSync($english->id);
        AnalyzeSessionJob::dispatchSync($empty->id);

        $this->assertSame([], $analyzer->transcripts);
        $this->assertNull($english->fresh()->analyzed_at);
        $this->assertNull($empty->fresh()->analyzed_at);
    }

    // --- endSession dispatch ---

    public function test_ending_a_mandarin_session_queues_the_analysis_after_the_response(): void
    {
        Bus::fake();
        $this->fakeAiClient();
        $this->fakeAnalyzer();
        $session = $this->mandarinSession();

        $summary = app(ConversationService::class)->endSession($session);

        $this->assertTrue($summary['analysis_queued']);
        $this->assertSame('Catatan feedback.', $summary['notes']); // existing feedback unchanged
        $this->assertNotNull($session->fresh()->ended_at);
        // phpunit.xml uses QUEUE_CONNECTION=sync, so it must be deferred, not run inline.
        Bus::assertDispatchedAfterResponse(AnalyzeSessionJob::class, fn ($job) => $job->sessionId === $session->id);
    }

    public function test_with_a_real_queue_the_analysis_is_pushed_normally(): void
    {
        Bus::fake();
        config(['queue.default' => 'database']);
        $this->fakeAiClient();
        $this->fakeAnalyzer();

        app(ConversationService::class)->endSession($this->mandarinSession());

        Bus::assertDispatched(AnalyzeSessionJob::class);
        Bus::assertNotDispatchedAfterResponse(AnalyzeSessionJob::class);
    }

    public function test_no_analysis_for_english_sessions_or_sessions_without_user_turns(): void
    {
        Bus::fake();
        $this->fakeAiClient();
        $this->fakeAnalyzer();

        $this->assertFalse(app(ConversationService::class)->endSession($this->mandarinSession(lang: 'english'))['analysis_queued']);
        $this->assertFalse(app(ConversationService::class)->endSession($this->mandarinSession(withUserTurn: false))['analysis_queued']);

        Bus::assertNothingDispatched();
    }

    public function test_no_analysis_when_no_analyzer_is_configured(): void
    {
        Bus::fake();
        $this->fakeAiClient(configured: false);
        // Real binding: Langflow empty (setUp) + AiClient not configured.
        $session = $this->mandarinSession();

        $summary = app(ConversationService::class)->endSession($session);

        $this->assertFalse(app(SessionAnalyzer::class)->isConfigured());
        $this->assertFalse($summary['analysis_queued']);
        Bus::assertNothingDispatched();

        Livewire::actingAs($session->user)->test(Chat::class, ['session' => $session->fresh()])
            ->assertDontSee('Analisis kesalahan');
    }

    public function test_a_dispatch_failure_never_stops_the_session_from_ending(): void
    {
        Exceptions::fake();
        $this->fakeAiClient();
        $this->fakeAnalyzer();
        Bus::shouldReceive('dispatchAfterResponse')->andThrow(new RuntimeException('queue down'));
        $session = $this->mandarinSession();

        $summary = app(ConversationService::class)->endSession($session);

        $this->assertFalse($summary['analysis_queued']);
        $this->assertNotNull($session->fresh()->ended_at);
        Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'queue down');
    }

    // --- Chat summary UI ---

    public function test_summary_shows_processing_and_polls_until_the_analysis_arrives(): void
    {
        Bus::fake();
        $this->fakeAiClient();
        $this->fakeAnalyzer();
        $session = $this->mandarinSession();

        $component = Livewire::actingAs($session->user)->test(Chat::class, ['session' => $session])
            ->call('finishSession')
            ->assertSee('Analisis kesalahan sedang diproses')
            ->assertSeeHtml('wire:poll.3s="pollAnalysis"');

        // The queued job finishes in the background...
        $session->update(['analyzed_at' => now(), 'error_analysis' => self::ANALYSIS]);

        $component->call('pollAnalysis')
            ->assertDontSee('sedang diproses')
            ->assertDontSeeHtml('wire:poll')
            ->assertSee('是 dengan kata sifat')
            ->assertSee('不 atau 没')
            ->assertSee('我不有钱')
            ->assertSee('我没有钱')
            ->assertSee('有 diingkari dengan 没.');
    }

    public function test_polling_stops_after_150_seconds_and_offers_a_reload_button(): void
    {
        Bus::fake();
        $this->fakeAiClient();
        $this->fakeAnalyzer();
        $session = $this->mandarinSession();

        $component = Livewire::actingAs($session->user)->test(Chat::class, ['session' => $session])
            ->call('finishSession');

        $this->travel(140)->seconds(); // a slow Langflow analysis (~74s seen) is still waited for

        $component->call('pollAnalysis')
            ->assertSee('sedang diproses')
            ->assertSeeHtml('wire:poll');

        $this->travel(11)->seconds();

        $component->call('pollAnalysis')
            ->assertSee('Analisis belum selesai')
            ->assertSee('Muat ulang')
            ->assertSeeHtml('wire:click="pollAnalysis"')
            ->assertDontSeeHtml('wire:poll');

        // The job finishes later; "Muat ulang" picks the result up.
        $session->update(['analyzed_at' => now(), 'error_analysis' => self::ANALYSIS]);

        $component->call('pollAnalysis')
            ->assertSee('我没有钱')
            ->assertDontSee('Muat ulang');
    }

    public function test_the_job_timeout_leaves_room_for_langflow_and_the_gemini_fallback(): void
    {
        $this->assertGreaterThanOrEqual(180, (new AnalyzeSessionJob(1))->timeout);
    }

    public function test_summary_shows_no_mistakes_and_failure_clearly(): void
    {
        $this->fakeAnalyzer();
        $clean = $this->mandarinSession();
        $clean->update(['ended_at' => now(), 'analyzed_at' => now(), 'error_analysis' => ['items' => [], 'catatan' => null]]);

        Livewire::actingAs($clean->user)->test(Chat::class, ['session' => $clean])
            ->assertSee('Tidak ada kesalahan yang terdeteksi')
            ->assertDontSeeHtml('wire:poll');

        $failed = $this->mandarinSession();
        $failed->update(['ended_at' => now(), 'analyzed_at' => now(), 'error_analysis' => null]);

        Livewire::actingAs($failed->user)->test(Chat::class, ['session' => $failed])
            ->assertSee('Analisis kesalahan gagal')
            ->assertDontSeeHtml('wire:poll');
    }

    public function test_summary_names_the_analyzer_and_old_sessions_show_nothing(): void
    {
        $this->fakeAnalyzer();

        foreach (['langflow' => 'Dianalisis oleh IBM Langflow', 'gemini' => 'Dianalisis oleh Gemini (cadangan)'] as $sumber => $label) {
            $session = $this->mandarinSession();
            $session->update(['ended_at' => now(), 'analyzed_at' => now(), 'error_analysis' => self::ANALYSIS + ['sumber' => $sumber]]);

            Livewire::actingAs($session->user)->test(Chat::class, ['session' => $session])
                ->assertSee('我没有钱')
                ->assertSee($label);
        }

        // Analyzed before 'sumber' existed: no label, no error.
        $old = $this->mandarinSession();
        $old->update(['ended_at' => now(), 'analyzed_at' => now(), 'error_analysis' => self::ANALYSIS]);

        Livewire::actingAs($old->user)->test(Chat::class, ['session' => $old])
            ->assertSee('我没有钱')
            ->assertDontSee('Dianalisis oleh');

        // A "no mistakes" result is labelled too.
        $clean = $this->mandarinSession();
        $clean->update(['ended_at' => now(), 'analyzed_at' => now(), 'error_analysis' => ['items' => [], 'catatan' => null, 'sumber' => 'langflow']]);

        Livewire::actingAs($clean->user)->test(Chat::class, ['session' => $clean])
            ->assertSee('Tidak ada kesalahan yang terdeteksi')
            ->assertSee('Dianalisis oleh IBM Langflow');
    }

    public function test_the_job_stores_sumber_from_the_analyzer(): void
    {
        $this->fakeAnalyzer(self::ANALYSIS + ['sumber' => 'gemini']);
        $session = $this->mandarinSession();

        AnalyzeSessionJob::dispatchSync($session->id);

        $this->assertSame(self::ANALYSIS + ['sumber' => 'gemini'], $session->fresh()->error_analysis);
    }

    public function test_an_ongoing_session_never_polls(): void
    {
        $this->fakeAnalyzer();
        $session = $this->mandarinSession();

        Livewire::actingAs($session->user)->test(Chat::class, ['session' => $session])
            ->assertDontSee('Analisis kesalahan')
            ->assertDontSeeHtml('wire:poll');
    }

    // --- analysis:retry-failed ---

    public function test_retry_dry_run_lists_failed_sessions_without_changing_them(): void
    {
        Queue::fake();
        $failed = $this->mandarinSession();
        $failed->update(['ended_at' => now(), 'analyzed_at' => now(), 'error_analysis' => null]);

        $this->artisan('analysis:retry-failed', ['--dry-run' => true])
            ->expectsOutputToContain('1 session(s) would be re-queued. Dry run: nothing changed.')
            ->assertSuccessful();

        $this->assertNotNull($failed->fresh()->analyzed_at);
        Queue::assertNothingPushed();
    }

    public function test_retry_requeues_only_failed_mandarin_sessions(): void
    {
        Queue::fake();
        $failed = $this->mandarinSession();
        $failed->update(['ended_at' => now(), 'analyzed_at' => now(), 'error_analysis' => null]);
        $done = $this->mandarinSession();
        $done->update(['ended_at' => now(), 'analyzed_at' => now(), 'error_analysis' => ['items' => [], 'catatan' => null]]);
        $pending = $this->mandarinSession(); // never analyzed
        $english = $this->mandarinSession(lang: 'english');
        $english->update(['analyzed_at' => now(), 'error_analysis' => null]);

        $this->artisan('analysis:retry-failed')
            ->expectsOutputToContain('1 session(s) re-queued.')
            ->assertSuccessful();

        $this->assertNull($failed->fresh()->analyzed_at); // cleared so the idempotent job runs again
        $this->assertNotNull($done->fresh()->analyzed_at);
        $this->assertNull($pending->fresh()->analyzed_at);
        $this->assertNotNull($english->fresh()->analyzed_at);
        Queue::assertPushed(AnalyzeSessionJob::class, 1);
        Queue::assertPushed(AnalyzeSessionJob::class, fn ($job) => $job->sessionId === $failed->id);

        // Nothing failed any more (the job hasn't run yet): a second run finds nothing.
        $this->artisan('analysis:retry-failed')
            ->expectsOutputToContain('No failed analyses to retry.')
            ->assertSuccessful();
    }

    public function test_retried_session_actually_gets_analyzed(): void
    {
        $this->fakeAnalyzer();
        $failed = $this->mandarinSession();
        $failed->update(['ended_at' => now(), 'analyzed_at' => now(), 'error_analysis' => null]);

        // QUEUE_CONNECTION=sync in tests: the re-dispatched job runs right away.
        $this->artisan('analysis:retry-failed')->assertSuccessful();

        $this->assertSame(self::ANALYSIS, $failed->fresh()->error_analysis);
    }
}
