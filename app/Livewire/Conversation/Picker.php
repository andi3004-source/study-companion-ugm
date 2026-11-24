<?php

namespace App\Livewire\Conversation;

use App\Services\ConversationService;
use App\Support\AiErrorMessage;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.study', ['navRingkas' => true])]
class Picker extends Component
{
    public string $lang = 'mandarin';

    public ?string $errorMessage = null;

    #[Computed]
    public function scenarios(): array
    {
        return app(ConversationService::class)->scenarios($this->lang);
    }

    public function selectScenario(string $scenarioKey)
    {
        abort_unless(in_array($this->lang, ['english', 'mandarin'], true), 422);
        $this->guardStart();
        $session = app(ConversationService::class)->startSession($this->lang, $scenarioKey);

        return $this->redirect(route('conversation.chat', $session));
    }

    public function startRandom()
    {
        $this->errorMessage = null;
        abort_unless(in_array($this->lang, ['english', 'mandarin'], true), 422);
        $this->guardStart();

        try {
            $session = app(ConversationService::class)->startRandomSession($this->lang);
        } catch (Throwable $e) {
            report($e);
            $this->errorMessage = AiErrorMessage::for($e);

            return null;
        }

        return $this->redirect(route('conversation.chat', $session));
    }

    private function guardStart(): void
    {
        abort_if(RateLimiter::tooManyAttempts('ngobrol:start:'.request()->ip(), 20), 429);
        RateLimiter::hit('ngobrol:start:'.request()->ip(), 3600);
    }

    public function render()
    {
        return view('livewire.conversation.picker');
    }
}
