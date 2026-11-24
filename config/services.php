<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
    ],

    // Confirmed against a live API call + ai.google.dev/gemini-api/docs/models
    // on 2026-09-10: gemini-2.5-flash 404s for new-user keys ("no longer
    // available to new users"), even though it's still listed in the docs.
    // Re-verify if this starts 404ing again — Gemini model names/availability
    // change over time.
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
    ],

    // Any OpenAI-compatible /chat/completions endpoint: a local router
    // (9router, LiteLLM, Ollama, llama.cpp, vLLM) or hosted OpenRouter.
    // Selected with AI_PROVIDER=openai_compatible (see the AiClient binding
    // in AppServiceProvider). The model name must match what the endpoint
    // serves — list them with: curl $BASE_URL/models -H "Authorization:
    // Bearer $OPENAI_COMPATIBLE_API_KEY".
    'openai_compatible' => [
        'key' => env('OPENAI_COMPATIBLE_API_KEY'),
        'model' => env('OPENAI_COMPATIBLE_MODEL', 'default'),
        'base_url' => env('OPENAI_COMPATIBLE_BASE_URL', 'http://127.0.0.1:20128/v1'),
    ],

    // Active Conversation Partner LLM provider: gemini (default),
    // anthropic, or openai_compatible. Only the binding + model change —
    // nothing in ConversationService needs to change.
    'ai_provider' => env('AI_PROVIDER', 'gemini'),

    // IBM Langflow flow for the end-of-session error analysis ("Kesalahan
    // terbanyakmu"). Used only when url AND flow_id are set; otherwise, and
    // whenever a Langflow call fails, the analysis goes through Gemini (see
    // the SessionAnalyzer binding in AppServiceProvider).
    'langflow' => [
        'url' => env('LANGFLOW_URL'),
        'flow_id' => env('LANGFLOW_FLOW_ID'),
        'api_key' => env('LANGFLOW_API_KEY'),
        // `?:` (not an env() default): an empty LANGFLOW_TIMEOUT= would cast to 0, which Guzzle reads as "no timeout".
        'timeout' => (int) (env('LANGFLOW_TIMEOUT') ?: 120),
    ],

    // Pronunciation checker: 'ml' calls pronunciation-service (OpenPronounce +
    // tone-me, see PronunciationChecker binding in AppServiceProvider) and
    // falls back to 'stt_match' automatically if that service is unreachable.
    // Set 'stt_match' here to skip the ML service entirely.
    'pronunciation_service' => [
        'url' => env('PRONUNCIATION_SERVICE_URL', 'http://localhost:8001'),
        'driver' => env('PRONUNCIATION_CHECKER_DRIVER', 'ml'),
    ],

];
