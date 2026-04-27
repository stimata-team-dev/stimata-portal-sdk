<?php

return [
    /*
    |--------------------------------------------------------------------------
    | STIMATA Portal Client ID
    |--------------------------------------------------------------------------
    |
    | The client ID obtained from STIMATA Portal when registering your
    | application. This is required for all OAuth2 flows.
    |
    */
    'client_id' => env('STIMATA_CLIENT_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | STIMATA Portal Client Secret
    |--------------------------------------------------------------------------
    |
    | The client secret obtained from STIMATA Portal when registering your
    | application. Keep this secure and never expose it in client-side code.
    |
    */
    'client_secret' => env('STIMATA_CLIENT_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | OAuth2 Redirect URI
    |--------------------------------------------------------------------------
    |
    | The callback URL where users will be redirected after authentication.
    | This must match exactly with the redirect URI registered in STIMATA Portal.
    |
    */
    'redirect_uri' => env('STIMATA_REDIRECT_URI', env('APP_URL').'/auth/callback'),

    /*
    |--------------------------------------------------------------------------
    | STIMATA Portal Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL of the STIMATA Portal API. This is where all OAuth2
    | and API requests will be sent.
    |
    */
    'base_url' => env('STIMATA_BASE_URL', 'http://localhost:9091/api'),

    /*
    |--------------------------------------------------------------------------
    | Default OAuth2 Scopes
    |--------------------------------------------------------------------------
    |
    | The default scopes to request when authenticating users. You can
    | override these on a per-request basis if needed.
    |
    | Available scopes:
    | - openid: Required for OpenID Connect
    | - profile: Access to user profile information
    | - email: Access to user email address
    | - read: Read access to resources
    | - write: Write access to resources
    |
    */
    'scopes' => [
        'openid',
        'profile',
        'email',
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Storage
    |--------------------------------------------------------------------------
    |
    | Configure how tokens should be stored. Options: 'session', 'cache', 'database'
    | Default: 'session'
    |
    */
    'token_storage' => env('STIMATA_TOKEN_STORAGE', 'session'),

    /*
    |--------------------------------------------------------------------------
    | Token Cache Driver
    |--------------------------------------------------------------------------
    |
    | If token_storage is set to 'cache', specify which cache driver to use.
    | This uses Laravel's cache configuration.
    |
    */
    'cache_driver' => env('STIMATA_CACHE_DRIVER', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Token Refresh Buffer
    |--------------------------------------------------------------------------
    |
    | Number of seconds before token expiry to trigger automatic refresh.
    | Default: 300 (5 minutes)
    |
    */
    'refresh_buffer' => env('STIMATA_REFRESH_BUFFER', 300),

    /*
    |--------------------------------------------------------------------------
    | Auto Refresh Tokens
    |--------------------------------------------------------------------------
    |
    | Automatically refresh access tokens when they expire. If set to false,
    | you will need to manually handle token refresh.
    |
    */
    'auto_refresh' => env('STIMATA_AUTO_REFRESH', true),

    /*
    |--------------------------------------------------------------------------
    | SSL Verification
    |--------------------------------------------------------------------------
    |
    | Enable SSL certificate verification for API requests. Should be true
    | in production for security. Can be disabled in development if needed.
    |
    */
    'ssl_verify' => env('STIMATA_SSL_VERIFY', true),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time in seconds to wait for API responses.
    | Default: 30 seconds
    |
    */
    'timeout' => env('STIMATA_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Middleware Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for built-in middleware.
    |
    */
    'middleware' => [
        // Redirect unauthorized users to this route
        'redirect_on_failure' => env('STIMATA_REDIRECT_ON_FAILURE', '/login'),

        // Session key for storing access token
        'session_token_key' => 'stimata_access_token',

        // Session key for storing refresh token
        'session_refresh_key' => 'stimata_refresh_token',

        // Session key for storing token expiry
        'session_expires_key' => 'stimata_token_expires_at',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable logging of OAuth2 flows and API requests for debugging.
    | WARNING: This may log sensitive information. Use only in development.
    |
    */
    'logging' => [
        'enabled' => env('STIMATA_LOGGING', false),
        'channel' => env('STIMATA_LOG_CHANNEL', 'stack'),
        'level' => env('STIMATA_LOG_LEVEL', 'debug'),
    ],
];
