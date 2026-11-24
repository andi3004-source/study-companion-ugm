<?php

namespace App\Livewire\Conversation;

use App\Models\ConversationSession;
use App\Services\ConversationService;
use App\Support\AiErrorMessage;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.study', ['navRingkas' => true])]
class Chat extends Component
{
    /**
     * How long the summary keeps polling for the queued error analysis before
     * giving up. Langflow alone has taken ~74s on a transcript with many mistakes.
     */
    public const ANALYSIS_POLL_SECONDS = 150;

    public ConversationSession $session;

    public string $newMessage = '';

    public ?string $errorMessage = null;

    /** Short text for the chat's role="status" region (screen-reader announcement only). */
    public ?string $statusMessage = null;

    public ?array $summary = null;

    public bool $autoPlay = false;

    /** Unix time the error analysis was queued by finishSession(); drives the polling window. */
    public ?int $analysisQueuedAt = null;

    public function mount(ConversationSession $session): void
    {
        abort_unless(in_array($session->id, session('ngobrol.sesi', []), true), 404);

        $this->session = $session;
    }

    private function ownedSession(): ConversationSession
    {
        abort_unless(in_array($this->session->id, session('ngobrol.sesi', []), true), 404);

        return ConversationSession::findOrFail($this->session->id);
    }

    #[Computed]
    public function turns()
    {
        return $this->ownedSession()->turns()->orderBy('created_at')->orderBy('id')->get();
    }

    #[Computed]
    public function scenario(): array
    {
        return app(ConversationService::class)->scenarioDisplay($this->ownedSession());
    }

    /** Same check that adds the HSK1 lock to the system prompt — see ConversationService::levelLocked(). */
    #[Computed]
    public function levelLocked(): bool
    {
        return app(ConversationService::class)->levelLocked($this->ownedSession());
    }

    #[Computed]
    public function remainingToday(): int
    {
        return app(ConversationService::class)->remainingMessagesToday();
    }

    /**
     * Shared entry point for both the text form and voice input (spec:
     * reuse the same method so rate-limit/history/validation never diverge
     * between the two input paths). $voiceText, when given, is the STT
     * transcript exactly as heard — never edited or auto-corrected — so a
     * consistently-mistranscribed word is a visible signal of a
     * pronunciation the user should drill.
     */
    public function sendMessage(?string $voiceText = null): void
    {
        $this->ownedSession();
        $this->errorMessage = null;
        $this->statusMessage = null;

        if ($this->session->ended_at) {
            return;
        }

        $content = trim($voiceText ?? $this->newMessage);

        if ($content === '') {
            $this->errorMessage = 'Pesan tidak boleh kosong.';

            return;
        }

        if (mb_strlen($content) > 500) {
            $this->errorMessage = 'Pesan maksimal 500 karakter.';

            return;
        }

        $service = app(ConversationService::class);

        $minuteKey = 'ngobrol:minute:'.request()->ip();
        if (RateLimiter::tooManyAttempts($minuteKey, 10)) {
            $this->errorMessage = 'Terlalu banyak pesan. Coba lagi sebentar.';

            return;
        }

        if ($service->remainingMessagesToday() <= 0) {
            $this->errorMessage = 'Kuota harian habis, lanjut besok.';

            return;
        }

        try {
            $assistantTurn = $service->sendMessage($this->session, $content, $voiceText !== null ? 'voice' : 'text');
        } catch (Throwable $e) {
            // $newMessage is left untouched so the typed text is not lost.
            report($e);
            $this->errorMessage = AiErrorMessage::for($e);

            return;
        }

        RateLimiter::hit($minuteKey, 60);
        RateLimiter::hit('ngobrol:day:'.request()->ip(), 86400);
        $this->newMessage = '';
        $this->statusMessage = 'Terkirim';
        $this->session->refresh();
        unset($this->turns, $this->remainingToday);
        $this->dispatch('message-sent');
        $this->dispatch('ai-replied', text: $assistantTurn->content, langCode: $this->scenario['target_lang_code']);
    }

    public function finishSession(): void
    {
        $this->ownedSession();
        abort_if(RateLimiter::tooManyAttempts('ngobrol:finish:'.request()->ip(), 20), 429);
        RateLimiter::hit('ngobrol:finish:'.request()->ip(), 3600);
        $this->summary = app(ConversationService::class)->endSession($this->session);
        $this->analysisQueuedAt = $this->summary['analysis_queued'] ? now()->timestamp : null;
        $this->session = $this->ownedSession()->refresh();
        unset($this->analysisState);
    }

    /**
     * "Kesalahan terbanyakmu" state for an ended Mandarin session: 'done' or
     * 'failed' once AnalyzeSessionJob has run (analyzed_at set; NULL
     * error_analysis = failed), 'pending' while it's queued and the
     * ANALYSIS_POLL_SECONDS polling window is open, 'timeout' after that, null = show nothing
     * (not Mandarin, not ended, or nothing was queued).
     */
    #[Computed]
    public function analysisState(): ?string
    {
        $this->ownedSession();
        if (! $this->session->ended_at || $this->session->lang !== 'mandarin') {
            return null;
        }

        if ($this->session->analyzed_at !== null) {
            return $this->session->error_analysis === null ? 'failed' : 'done';
        }

        if ($this->analysisQueuedAt === null) {
            return null;
        }

        return now()->timestamp - $this->analysisQueuedAt >= self::ANALYSIS_POLL_SECONDS ? 'timeout' : 'pending';
    }

    /** wire:poll target while the analysis is pending, and the "Muat ulang" button after the window — just re-reads the session. */
    public function pollAnalysis(): void
    {
        $this->session = $this->ownedSession()->refresh();
        unset($this->analysisState);
    }

    public function render()
    {
        return view('livewire.conversation.chat', [
            'dailyLimit' => ConversationService::dailyMessageLimit(),
        ]);
    }
}
