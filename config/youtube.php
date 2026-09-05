<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Live Detection Provider
    |--------------------------------------------------------------------------
    |
    | The provider to use for detecting live streams.
    | Options: youtube_direct, youtube_api, holodex
    |
    */
    'provider' => env('YOUTUBE_DETECTION_PROVIDER', 'youtube_direct'),

    /*
    |--------------------------------------------------------------------------
    | Monitoring Settings
    |--------------------------------------------------------------------------
    */

    // How often to check channels (in seconds)
    'polling_interval' => env('YOUTUBE_POLLING_INTERVAL', 120),

    // Maximum number of channels to monitor
    'max_channels' => env('YOUTUBE_MAX_CHANNELS', 100),

    // Queue for monitoring jobs
    'monitoring_queue' => env('YOUTUBE_MONITORING_QUEUE', 'youtube'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Request Settings
    |--------------------------------------------------------------------------
    */

    // Request timeout in seconds
    'timeout' => env('YOUTUBE_TIMEOUT', 15),

    // Maximum retries on failure
    'max_retries' => env('YOUTUBE_MAX_RETRIES', 2),

    // Delay between requests in milliseconds
    'request_delay' => env('YOUTUBE_REQUEST_DELAY', 500),

    // Concurrency level
    'concurrency' => env('YOUTUBE_CONCURRENCY', 5),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting & Cooldown
    |--------------------------------------------------------------------------
    */

    // Cooldown period after challenge detection (seconds)
    'challenge_cooldown' => env('YOUTUBE_CHALLENGE_COOLDOWN', 300),

    // Cooldown period after rate limit (seconds)
    'rate_limit_cooldown' => env('YOUTUBE_RATE_LIMIT_COOLDOWN', 120),

    /*
    |--------------------------------------------------------------------------
    | YouTube Data API (Optional Fallback)
    |--------------------------------------------------------------------------
    */

    'api' => [
        'key' => env('YOUTUBE_API_KEY'),
        'quota' => env('YOUTUBE_API_QUOTA', 10000),
    ],

];
