<?php

declare(strict_types=1);

return [
    /*
    | Modelo de usuario del host. Debe implementar Alma\Auth\Contracts\AuthenticatableUser.
    */
    'user_model' => env('ALMA_AUTH_USER_MODEL'),
];
