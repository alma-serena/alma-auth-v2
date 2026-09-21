<?php

declare(strict_types=1);

namespace Alma\Auth\Http\Controllers;

use Alma\Auth\Contracts\AuthenticatableUser;
use Alma\Auth\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    public function __construct(private AuthService $auth) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $result = $this->auth->attemptLogin($credentials['email'], $credentials['password']);

        if (! $result->success || $result->user === null) {
            throw ValidationException::withMessages([
                'email' => [__('alma-auth::messages.invalid_credentials')],
            ]);
        }

        if ($result->requiresTwoFactor) {
            $token = $result->user->createToken('2fa-pending', ['2fa:verify'])->plainTextToken;

            return response()->json([
                'status' => '2fa_required',
                'token' => $token,
            ]);
        }

        $token = $result->user->createToken('auth', ['*'])->plainTextToken;

        return response()->json([
            'status' => 'authenticated',
            'token' => $token,
        ]);
    }

    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|size:6']);

        /** @var AuthenticatableUser $user */
        $user = $request->user();

        if (! $this->auth->verifyTwoFactorChallenge($user, $request->string('code')->toString())) {
            return response()->json(['status' => 'invalid_code'], 401);
        }

        $user->tokens()->where('id', $user->currentAccessToken()?->getKey())->delete();

        $token = $user->createToken('auth', ['*'])->plainTextToken;

        return response()->json([
            'status' => 'authenticated',
            'token' => $token,
        ]);
    }

    public function enrollTwoFactor(Request $request): JsonResponse
    {
        /** @var AuthenticatableUser $user */
        $user = $request->user();
        $secret = $this->auth->startTwoFactorEnrollment($user);

        return response()->json([
            'status' => 'enrollment_started',
            'secret' => $secret,
        ]);
    }

    public function confirmTwoFactor(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|size:6']);

        /** @var AuthenticatableUser $user */
        $user = $request->user();

        if (! $this->auth->confirmTwoFactorEnrollment($user, $request->string('code')->toString())) {
            return response()->json(['status' => 'invalid_code'], 422);
        }

        return response()->json(['status' => 'two_factor_enabled']);
    }
}
