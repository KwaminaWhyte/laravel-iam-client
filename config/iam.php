<?php

return [

    /*
    |--------------------------------------------------------------------------
    | IAM Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to the Adamus IAM service for centralized
    | authentication and authorization.
    |
    */

    'base_url' => rtrim(env('IAM_BASE_URL', 'http://localhost:8000/api/v1'), '/') . '/',

    /*
    |--------------------------------------------------------------------------
    | Default Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings for IAM integration
    |
    */

    'timeout' => env('IAM_TIMEOUT', 10),

    'verify_ssl' => env('IAM_VERIFY_SSL', true),

    /*
    |--------------------------------------------------------------------------
    | Guard Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the IAM authentication guard
    |
    */

    'guard' => 'iam',

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent user model used for local user records
    |
    */

    'user_model' => env('IAM_USER_MODEL', \App\Models\User::class),

    /*
    |--------------------------------------------------------------------------
    | Token Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for handling IAM tokens
    |
    */

    'token_header' => 'Authorization',

    'token_prefix' => 'Bearer',

];