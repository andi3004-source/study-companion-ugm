<?php

/**
 * Conversation Partner (Ngobrol) settings.
 *
 * daily_message_limit: how many messages one browser may send to the AI per day.
 * Read through ConversationService::dailyMessageLimit(), which falls back to
 * ConversationService::DAILY_MESSAGE_LIMIT (50) unless the value is a whole
 * number between 1 and ConversationService::MAX_DAILY_MESSAGE_LIMIT.
 * ai_daily_token_budget: global limit across browsers and providers.
 * Calls without usage count at least 1000 estimated tokens.
 */
return [
    'daily_message_limit' => env('DAILY_MESSAGE_LIMIT', 50),
    'ai_daily_token_budget' => env('AI_DAILY_TOKEN_BUDGET', 500000),
];
