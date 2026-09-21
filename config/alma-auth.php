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
];
