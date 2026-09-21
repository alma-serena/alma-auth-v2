<?php

declare(strict_types=1);

use Alma\Auth\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

Route::prefix('api/alma-auth')->group(function () {
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/passkeys/login/options', [AuthController::class, 'passkeyLoginOptions']);
        Route::post('/passkeys/login', [AuthController::class, 'passkeyLogin']);
    });

    Route::middleware(['auth:sanctum', CheckAbilities::class.':2fa:verify'])->group(function () {
        Route::post('/2fa/verify', [AuthController::class, 'verifyTwoFactor']);
    });

    Route::middleware(['auth:sanctum', CheckAbilities::class.':*'])->group(function () {
        Route::post('/step-up', [AuthController::class, 'stepUp']);
        Route::get('/passkeys', [AuthController::class, 'listPasskeys']);
    });

    Route::middleware(['auth:sanctum', CheckAbilities::class.':*', 'alma.recent'])->group(function () {
        Route::post('/2fa/enroll', [AuthController::class, 'enrollTwoFactor']);
        Route::post('/2fa/confirm', [AuthController::class, 'confirmTwoFactor']);
        Route::post('/email/change', [AuthController::class, 'requestEmailChange']);
        Route::post('/email/confirm', [AuthController::class, 'confirmEmailChange']);
        Route::post('/passkeys/register/options', [AuthController::class, 'passkeyRegisterOptions']);
        Route::post('/passkeys/register', [AuthController::class, 'passkeyRegister']);
        Route::delete('/passkeys/{passkey}', [AuthController::class, 'revokePasskey']);
    });
});
