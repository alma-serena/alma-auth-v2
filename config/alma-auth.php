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
];
