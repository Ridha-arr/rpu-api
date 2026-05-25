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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'keycloak' => [
        'base_url' => rtrim((string) env('KEYCLOAK_BASE_URL'), '/'),
        'realm' => env('KEYCLOAK_REALM'),
        'realms' => env('KEYCLOAK_REALM'),
        'issuer' => env(
            'KEYCLOAK_ISSUER',
            env('KEYCLOAK_BASE_URL') && env('KEYCLOAK_REALM')
                ? preg_replace('/\/realms$/', '', rtrim(env('KEYCLOAK_BASE_URL'), '/')) . '/realms/' . env('KEYCLOAK_REALM')
                : null
        ),
        'jwks_url' => env(
            'KEYCLOAK_JWKS_URL',
            env('KEYCLOAK_BASE_URL') && env('KEYCLOAK_REALM')
                ? preg_replace('/\/realms$/', '', rtrim(env('KEYCLOAK_BASE_URL'), '/')) . '/realms/' . env('KEYCLOAK_REALM') . '/protocol/openid-connect/certs'
                : null
        ),
        'client_id' => env('KEYCLOAK_CLIENT_ID'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
        'redirect' => env('KEYCLOAK_REDIRECT_URI'),
        'redirect_uris' => array_filter(array_map('trim', explode(',', (string) env('KEYCLOAK_REDIRECT_URIS', env('KEYCLOAK_REDIRECT_URI', ''))))),
        'require_email_verified' => env('KEYCLOAK_REQUIRE_EMAIL_VERIFIED', false),
        'default_level' => env('KEYCLOAK_DEFAULT_USER_LEVEL', 3),
        'token_url' => env(
            'KEYCLOAK_TOKEN_URL',
            env('KEYCLOAK_BASE_URL') && env('KEYCLOAK_REALM')
                ? preg_replace('/\/realms$/', '', rtrim(env('KEYCLOAK_BASE_URL'), '/')) . '/realms/' . env('KEYCLOAK_REALM') . '/protocol/openid-connect/token'
                : null
        ),
        'userinfo_url' => env(
            'KEYCLOAK_USERINFO_URL',
            env('KEYCLOAK_BASE_URL') && env('KEYCLOAK_REALM')
                ? preg_replace('/\/realms$/', '', rtrim(env('KEYCLOAK_BASE_URL'), '/')) . '/realms/' . env('KEYCLOAK_REALM') . '/protocol/openid-connect/userinfo'
                : null
        ),
    ],

    'rpu_sync' => [
        'url' => env('RPU_SYNC_URL'),
        'token' => env('RPU_SYNC_TOKEN'),
    ],

    'publication_api' => [
        'key' => env('PUBLICATION_API_KEY'),
    ],

    'sister' => [
        'url' => rtrim((string) env('SISTER_API_URL'), '/'),
        'username' => env('SISTER_API_USERNAME'),
        'password' => env('SISTER_API_PASSWORD'),
        'id_pengguna' => env('SISTER_API_ID'),
        'token_cache_seconds' => env('SISTER_API_TOKEN_CACHE_SECONDS', 3600),
    ],
    'sdm' => [
        'url' => rtrim((string) env('SDM_API_URL'), '/'),
        'key' => env('SDM_API_KEY'),
    ],

];
