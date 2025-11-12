<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Activity Logging Toggle
    |--------------------------------------------------------------------------
    |
    | This flag allows you to disable outbound activity logging without
    | uninstalling the package. You can toggle this at runtime with an env var.
    |
    */

    'enabled' => env('ACTIVITY_LOGGER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Remote API Endpoint
    |--------------------------------------------------------------------------
    |
    | Base URL that receives activity payloads. A trailing slash is optional.
    | Example: https://logs.example.com/api/activity
    |
    */

    'endpoint' => env('ACTIVITY_LOGGER_ENDPOINT'),

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Supported auth strategies: "token", "basic", or "none".
    | - token: attaches an Authorization: Bearer {token} header.
    | - basic: uses HTTP basic auth with username/password.
    | - none: sends no auth headers beyond defaults.
    |
    */

    'auth' => [
        'type' => env('ACTIVITY_LOGGER_AUTH_TYPE', 'token'),
        'token' => env('ACTIVITY_LOGGER_TOKEN'),
        'username' => env('ACTIVITY_LOGGER_BASIC_USERNAME'),
        'password' => env('ACTIVITY_LOGGER_BASIC_PASSWORD'),
        'headers' => [
            // 'X-Custom-Header' => env('ACTIVITY_LOGGER_CUSTOM_HEADER'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Delivery
    |--------------------------------------------------------------------------
    |
    | Configure the queue connection, queue name, and optional dispatch delay.
    | Ensure that a queue worker is running for the selected connection.
    |
    */

    'queue' => [
        'connection' => env('ACTIVITY_LOGGER_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
        'name' => env('ACTIVITY_LOGGER_QUEUE', 'activity-logs'),
        'delay' => env('ACTIVITY_LOGGER_QUEUE_DELAY', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Options
    |--------------------------------------------------------------------------
    |
    | Customize HTTP client behavior: timeouts, retries, TLS verification,
    | and optional exponential backoff multiplier.
    |
    */

    'http' => [
        'timeout' => env('ACTIVITY_LOGGER_HTTP_TIMEOUT', 10),
        'verify' => env('ACTIVITY_LOGGER_HTTP_VERIFY', true),
        'retry' => [
            'attempts' => env('ACTIVITY_LOGGER_RETRY_ATTEMPTS', 3),
            'backoff' => env('ACTIVITY_LOGGER_RETRY_BACKOFF', 5),
            'max_backoff' => env('ACTIVITY_LOGGER_RETRY_MAX_BACKOFF', 60),
            'jitter' => env('ACTIVITY_LOGGER_RETRY_JITTER', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Toggle optional integrations like automatic authentication event logging
    | or the global request activity middleware.
    |
    */

    'features' => [
        'log_authentication_events' => env('ACTIVITY_LOGGER_FEATURE_AUTH_EVENTS', true),
        'autoload_middleware' => env('ACTIVITY_LOGGER_FEATURE_LOAD_MIDDLEWARE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payload Scrubbing
    |--------------------------------------------------------------------------
    |
    | Sensitive keys listed in the deny-list will be removed from payloads
    | before transmission. You can disable scrubbing or extend the list.
    |
    */

    'scrub' => [
        'enabled' => env('ACTIVITY_LOGGER_SCRUB_ENABLED', true),
        'denylist' => [
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'secret',
            'authorization',
        ],
    ],
];