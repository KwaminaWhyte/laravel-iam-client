<?php

return [
    /*
    |--------------------------------------------------------------------------
    | IAM Server Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the IAM server implementation. This file is used when
    | the package is installed in server mode.
    |
    */

    // JWT Configuration
    'jwt' => [
        'secret' => env('JWT_SECRET'),
        'ttl' => env('JWT_TTL', 60), // Token lifetime in minutes
        'refresh_ttl' => env('JWT_REFRESH_TTL', 20160), // Refresh token lifetime in minutes (14 days)
        'algo' => env('JWT_ALGO', 'HS256'),
    ],

    // Password Configuration
    'password' => [
        'min_length' => env('IAM_PASSWORD_MIN_LENGTH', 8),
        'require_uppercase' => env('IAM_PASSWORD_REQUIRE_UPPERCASE', true),
        'require_numbers' => env('IAM_PASSWORD_REQUIRE_NUMBERS', true),
        'require_special_chars' => env('IAM_PASSWORD_REQUIRE_SPECIAL', false),
    ],

    // Session Configuration
    'session' => [
        'timeout' => env('IAM_SESSION_TIMEOUT', 120), // minutes
        'simultaneous_sessions' => env('IAM_SIMULTANEOUS_SESSIONS', 3),
    ],

    // Audit Logging
    'audit' => [
        'enabled' => env('IAM_AUDIT_ENABLED', true),
        'log_sensitive_data' => env('IAM_AUDIT_LOG_SENSITIVE', false),
    ],

    // Security
    'security' => [
        'max_login_attempts' => env('IAM_MAX_LOGIN_ATTEMPTS', 5),
        'lockout_duration' => env('IAM_LOCKOUT_DURATION', 15), // minutes
        'two_factor_enabled' => env('IAM_2FA_ENABLED', false),
    ],

    // API
    'api' => [
        'rate_limit' => env('IAM_API_RATE_LIMIT', 60), // requests per minute
        'pagination_default' => env('IAM_PAGINATION_DEFAULT', 15),
        'pagination_max' => env('IAM_PAGINATION_MAX', 100),
    ],
];
