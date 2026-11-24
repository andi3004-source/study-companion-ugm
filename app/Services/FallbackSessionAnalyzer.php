<?php

namespace App\Services;

use App\Contracts\SessionAnalyzer;
use App\Exceptions\LangflowRequestException;
use Throwable;

/**
 * Tries $primary (Langflow) and, on any failure — HTTP error, timeout,
 * missing/garbled output — reports it and asks $fallback (Gemini) instead,
 * so a Langflow outage costs one report, not the learner's analysis.
 *
 * deferringTransientFailures() is for AnalyzeSessionJob while it still has
 * queue attempts left: a transient Langflow failure (see
 * LangflowRequestException) is rethrown so the job can retry Langflow later,
 * instead of falling back right away.
 */
class FallbackSessionAnalyzer implements SessionAnalyzer
{
    private bool $deferTransient = false;

    public function __construct(
        private readonly SessionAnalyzer $primary,
        private readonly SessionAnalyzer $fallback,
    ) {}

    public function deferringTransientFailures(): static
    {
        $clone = clone $this;
        $clone->deferTransient = true;

        return $clone;
    }

    public function isConfigured(): bool
    {
        return $this->primary->isConfigured() || $this->fallback->isConfigured();
    }

    public function analyze(string $transcript, ?int $sessionId = null): array
    {
        try {
            return $this->primary->analyze($transcript, $sessionId);
        } catch (Throwable $e) {
            if ($e instanceof AiBudgetExceeded) {
                throw $e;
            }
            if ($this->deferTransient && $e instanceof LangflowRequestException && $e->transient) {
                throw $e;
            }

            report($e);

            return $this->fallback->analyze($transcript, $sessionId);
        }
    }
}
