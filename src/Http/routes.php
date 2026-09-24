<?php

declare(strict_types=1);

use Alma\Auth\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

Route::prefix('api/alma-auth')->group(function () {
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/passkeys/login/options', [AuthController::class, 'passkeyLoginOptions']);
        Route::post('/passkeys/login', [AuthController::class, 'passkeyLogin']);
        Route::post('/oauth/login', [AuthController::class, 'oauthLogin']);
    });

    Route::middleware(['auth:sanctum', CheckAbilities::class.':2fa:verify'])->group(function () {
        Route::post('/2fa/verify', [AuthController::class, 'verifyTwoFactor']);
    });

    Route::middleware(['auth:sanctum', CheckAbilities::class.':*'])->group(function () {
        Route::post('/step-up', [AuthController::class, 'stepUp']);
        Route::get('/passkeys', [AuthController::class, 'listPasskeys']);
        Route::get('/devices', [AuthController::class, 'listTrustedDevices']);
        Route::get('/legal/consents', [AuthController::class, 'listConsents']);
        Route::post('/legal/consent', [AuthController::class, 'recordConsent']);
        Route::get('/roles', [AuthController::class, 'listRoles']);
        Route::get('/oauth/links', [AuthController::class, 'listOAuthLinks']);
    });

    Route::middleware(['auth:sanctum', CheckAbilities::class.':*', 'alma.recent'])->group(function () {
        Route::post('/2fa/enroll', [AuthController::class, 'enrollTwoFactor']);
        Route::post('/2fa/confirm', [AuthController::class, 'confirmTwoFactor']);
        Route::post('/email/change', [AuthController::class, 'requestEmailChange']);
        Route::post('/email/confirm', [AuthController::class, 'confirmEmailChange']);
        Route::post('/passkeys/register/options', [AuthController::class, 'passkeyRegisterOptions']);
        Route::post('/passkeys/register', [AuthController::class, 'passkeyRegister']);
        Route::delete('/passkeys/{passkey}', [AuthController::class, 'revokePasskey']);
        Route::delete('/devices/{device}', [AuthController::class, 'revokeTrustedDevice']);
        Route::post('/password/change', [AuthController::class, 'changePassword']);
        Route::post('/sessions/revoke', [AuthController::class, 'revokeSessions']);
        Route::post('/oauth/link', [AuthController::class, 'oauthLink']);
        Route::delete('/oauth/{provider}', [AuthController::class, 'oauthUnlink']);
    });
});
