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

    /*
    |--------------------------------------------------------------------------
    | AI provider
    |--------------------------------------------------------------------------
    |
    | The single provider this application talks to. The key lives only in .env
    | and is sent as the x-goog-api-key header, never in a URL or in client-side
    | code. When 'key' is empty the AI features report themselves as not
    | configured and the rest of the application keeps working normally.
    |
    | 'model' defaults to a specific fast Flash model, chosen by measurement:
    | the gemini-flash-latest alias resolved to a much newer model that answered
    | in 28 seconds and intermittently returned HTTP 503 "high demand". A form
    | button that waits half a minute is not usable, and this application makes
    | three small structured calls per session.
    |
    | Set GEMINI_MODEL to change it. If a model is ever retired the API answers
    | 404 and the user is told to check this setting, so the failure is legible
    | rather than mysterious.
    |
    */

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

        // Seconds. Connection attempts give up sooner than the whole request, so
        // an unreachable host fails fast instead of hanging a page.
        'timeout' => (int) env('GEMINI_TIMEOUT', 45),
        'connect_timeout' => (int) env('GEMINI_CONNECT_TIMEOUT', 10),

        // Cost guard: the longest prompt text that will ever be sent.
        'max_input_length' => (int) env('GEMINI_MAX_INPUT_LENGTH', 12000),

        // Per-user request ceiling for the AI endpoints, per minute.
        'rate_limit_per_minute' => (int) env('GEMINI_RATE_LIMIT', 10),

        'temperature' => (float) env('GEMINI_TEMPERATURE', 0.2),

        // Busy-provider responses (503/429) are retried, because they generate
        // nothing and so cost nothing to repeat. 3 attempts with a short pause
        // absorbs the intermittent "high demand" spikes seen on this provider.
        'retry_attempts' => (int) env('GEMINI_RETRY_ATTEMPTS', 3),
        'retry_delay_ms' => (int) env('GEMINI_RETRY_DELAY_MS', 700),
    ],

    /*
    |--------------------------------------------------------------------------
    | Embeddings (semantic search)
    |--------------------------------------------------------------------------
    |
    | gemini-embedding-001 is the model this API key can actually reach;
    | text-embedding-004 is not offered. It returns 3072 dimensions by default,
    | which is reduced here to keep prompt_embeddings compact. Cosine similarity
    | is scale invariant, so a truncated vector needs no re-normalisation.
    |
    | The stored dimension count is recorded per row, and a stored vector from a
    | different model or width is treated as stale and regenerated.
    |
    | Rate limiting is the practical constraint: batchEmbedContents bills per
    | TEXT, not per call, and this key allows roughly 100 embedding items per
    | minute (measured: 8 + 50 succeeded, the next 50 returned HTTP 429). The
    | defaults below therefore pace the sync at about 80 items a minute rather
    | than firing 200 at once, which just fails.
    |
    */

    'embeddings' => [
        'model' => env('GEMINI_EMBEDDING_MODEL', 'gemini-embedding-001'),
        'dimensions' => (int) env('GEMINI_EMBEDDING_DIMENSIONS', 768),
        'batch_size' => (int) env('GEMINI_EMBEDDING_BATCH', 20),
        // Pause between batches so the per-minute item allowance is respected.
        'batch_pause_ms' => (int) env('GEMINI_EMBEDDING_PAUSE_MS', 15000),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
