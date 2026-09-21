<?php

declare(strict_types=1);

return [
    /*
    | Modelo de usuario del host. Debe implementar Alma\Auth\Contracts\AuthenticatableUser.
    */
    'user_model' => env('ALMA_AUTH_USER_MODEL'),

    'refresh_token_ttl_days' => (int) env('ALMA_AUTH_REFRESH_TTL_DAYS', 30),
    'refresh_family_ttl_days' => (int) env('ALMA_AUTH_REFRESH_FAMILY_TTL_DAYS', 90),

    'lockout_max_attempts' => (int) env('ALMA_AUTH_LOCKOUT_MAX_ATTEMPTS', 5),
    'lockout_decay_minutes' => (int) env('ALMA_AUTH_LOCKOUT_DECAY_MINUTES', 15),

    /*
    | Clave HMAC de la cadena de auditoría (AUTH-07). Mínimo 32 bytes.
    | Nunca versionar el valor; solo el nombre de la variable de entorno.
    */
    'hmac_key' => env('ALMA_AUTH_HMAC_KEY'),

    'step_up_minutes' => (int) env('ALMA_AUTH_STEP_UP_MINUTES', 10),
    'email_change_ttl_minutes' => (int) env('ALMA_AUTH_EMAIL_CHANGE_TTL_MINUTES', 60),

    /*
    | Passkeys / WebAuthn (AUTH-04). Origins con esquema (http://localhost, https://app.example).
    */
    'passkey_rp_id' => env('ALMA_AUTH_PASSKEY_RP_ID', 'localhost'),
    'passkey_rp_name' => env('ALMA_AUTH_PASSKEY_RP_NAME', 'ALMA Auth'),
    'passkey_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ALMA_AUTH_PASSKEY_ORIGINS', 'http://localhost')),
    ))),
    'passkey_timeout_ms' => (int) env('ALMA_AUTH_PASSKEY_TIMEOUT_MS', 60_000),
    'passkey_challenge_ttl_minutes' => (int) env('ALMA_AUTH_PASSKEY_CHALLENGE_TTL_MINUTES', 5),

    'trusted_device_ttl_days' => (int) env('ALMA_AUTH_TRUSTED_DEVICE_TTL_DAYS', 90),
];
