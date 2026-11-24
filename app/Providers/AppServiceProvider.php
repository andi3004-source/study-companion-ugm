<?php

namespace App\Providers;

use App\Contracts\AiClient;
use App\Contracts\ObjectRecognizer;
use App\Contracts\PronunciationChecker;
use App\Contracts\SessionAnalyzer;
use App\Services\AnthropicClient;
use App\Services\FallbackSessionAnalyzer;
use App\Services\GeminiClient;
use App\Services\GeminiSessionAnalyzer;
use App\Services\LangflowSessionAnalyzer;
use App\Services\MlPronunciationChecker;
use App\Services\OpenAiCompatibleClient;
use App\Services\Scan\GeminiObjectRecognizer;
use App\Services\Scan\NullObjectRecognizer;
use App\Services\Scan\OpenAiVisionRecognizer;
use App\Services\SttMatchPronunciationChecker;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Active Conversation Partner LLM provider + its model name, set together
        // so ConversationService (config('services.active_ai_model')) never has to
        // hardcode a provider-specific config key. AI_PROVIDER=openai_compatible
        // talks to any OpenAI-compatible endpoint (local 9router, LiteLLM,
        // Ollama, ...); AI_PROVIDER=anthropic goes back to Claude — nothing in
        // ConversationService needs to change either way.
        $provider = config('services.ai_provider', 'gemini');
        $client = match ($provider) {
            'anthropic' => AnthropicClient::class,
            'openai_compatible' => OpenAiCompatibleClient::class,
            default => GeminiClient::class,
        };
        $modelKey = match ($provider) {
            'anthropic' => 'services.anthropic.model',
            'openai_compatible' => 'services.openai_compatible.model',
            default => 'services.gemini.model',
        };
        $this->app->bind(AiClient::class, $client);
        config(['services.active_ai_model' => config($modelKey)]);

        // End-of-session error analysis: the Langflow flow when it's configured
        // (falling back to Gemini on any Langflow failure), otherwise Gemini.
        $this->app->bind(SessionAnalyzer::class, function ($app) {
            $gemini = $app->make(GeminiSessionAnalyzer::class);
            $langflow = $app->make(LangflowSessionAnalyzer::class);

            return $langflow->isConfigured() ? new FallbackSessionAnalyzer($langflow, $gemini) : $gemini;
        });

        // PRONUNCIATION_CHECKER_DRIVER=stt_match turns off the ML microservice
        // entirely (no HTTP call attempted at all, not even one that fails) —
        // useful if pronunciation-service turns out too unstable to run.
        // Default 'ml' still degrades to SttMatchPronunciationChecker per-request
        // on its own if the service is unreachable (see MlPronunciationChecker).
        $this->app->bind(PronunciationChecker::class, function ($app) {
            return match (config('services.pronunciation_service.driver')) {
                'stt_match' => $app->make(SttMatchPronunciationChecker::class),
                default => $app->make(MlPronunciationChecker::class),
            };
        });

        // "Raba lalu dengar": Gemini vision when a key is set, otherwise the
        // OpenAI-compatible vision endpoint when ITS key is set (local
        // 9router, OpenRouter, ...), otherwise a recognizer that always
        // answers tidak_yakin instead of guessing.
        $this->app->bind(ObjectRecognizer::class, function ($app) {
            if (filled(config('services.gemini.key'))) {
                return $app->make(GeminiObjectRecognizer::class);
            }

            return filled(config('services.openai_compatible.key'))
                ? $app->make(OpenAiVisionRecognizer::class)
                : $app->make(NullObjectRecognizer::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Behind a TLS-terminating proxy (Caddy in docker-compose.yml): trust its
        // X-Forwarded-* headers so request()->secure() and client IPs are right,
        // and always generate https:// URLs. Both off unless set in .env.
        if (filled(config('app.trusted_proxies'))) {
            $proxies = config('app.trusted_proxies');
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('voice-command', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('scan-benda', function (Request $request) {
            return Limit::perMinute(max(1, (int) config('scan.rate_limit_per_minute', 6)))
                ->by($request->user()?->id ?: $request->ip());
        });
    }
}
