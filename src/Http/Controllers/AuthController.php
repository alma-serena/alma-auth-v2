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
            'device_fingerprint' => 'sometimes|string|max:255',
        ]);

        $result = $this->auth->attemptLogin(
            $credentials['email'],
            $credentials['password'],
            $request->ip() ?? '0.0.0.0',
        );

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

        return $this->authenticatedResponse(
            $result->user,
            $credentials['device_fingerprint'] ?? '',
            $request->ip(),
        );
    }

    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|size:6',
            'device_fingerprint' => 'sometimes|string|max:255',
        ]);

        /** @var AuthenticatableUser $user */
        $user = $request->user();

        if (! $this->auth->verifyTwoFactorChallenge($user, $data['code'])) {
            return response()->json(['status' => 'invalid_code'], 401);
        }

        $user->tokens()->where('id', $user->currentAccessToken()?->getKey())->delete();

        return $this->authenticatedResponse(
            $user,
            $data['device_fingerprint'] ?? '',
            $request->ip(),
        );
    }

    public function refresh(Request $request): JsonResponse
    {
        $data = $request->validate([
            'refresh_token' => 'required|string',
            'device_fingerprint' => 'sometimes|string|max:255',
        ]);

        $rotation = $this->auth->rotateRefreshToken(
            $data['refresh_token'],
            $data['device_fingerprint'] ?? '',
        );

        if (! $rotation->success || $rotation->refreshToken === null || $rotation->userId === null) {
            return response()->json([
                'status' => $rotation->failure ?? 'invalid_refresh_token',
            ], 401);
        }

        $user = $this->auth->resolveUserById($rotation->userId);
        if ($user === null) {
            return response()->json(['status' => 'invalid_refresh_token'], 401);
        }

        $access = $user->createToken('auth', ['*']);

        return response()->json([
            'status' => 'authenticated',
            'token' => $access->plainTextToken,
            'refresh_token' => $rotation->refreshToken,
        ]);
    }

    public function stepUp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => 'required|string',
        ]);

        /** @var AuthenticatableUser $user */
        $user = $request->user();
        $token = $user->currentAccessToken();
        if ($token === null) {
            return response()->json(['status' => 'unauthenticated'], 401);
        }

        if (! $this->auth->confirmStepUp($user, $data['password'], $token->getKey())) {
            return response()->json(['status' => 'invalid_credentials'], 401);
        }

        return response()->json(['status' => 'step_up_ok']);
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

    public function requestEmailChange(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
        ]);

        /** @var AuthenticatableUser $user */
        $user = $request->user();
        $this->auth->requestEmailChange($user, $data['email']);

        return response()->json(['status' => 'email_change_requested']);
    }

    public function confirmEmailChange(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string',
        ]);

        /** @var AuthenticatableUser $user */
        $user = $request->user();

        if (! $this->auth->confirmEmailChange($user, $data['code'])) {
            return response()->json(['status' => 'invalid_code'], 422);
        }

        return response()->json(['status' => 'email_changed']);
    }

    private function authenticatedResponse(
        AuthenticatableUser $user,
        string $deviceFingerprint,
        ?string $ip,
    ): JsonResponse {
        $access = $user->createToken('auth', ['*']);
        $this->auth->markStepUpForToken($access->accessToken->getKey());
        $refresh = $this->auth->issueRefreshToken($user, $deviceFingerprint, $ip);

        return response()->json([
            'status' => 'authenticated',
            'token' => $access->plainTextToken,
            'refresh_token' => $refresh,
        ]);
    }
}
