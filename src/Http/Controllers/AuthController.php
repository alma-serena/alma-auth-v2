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
            $credentials['device_fingerprint'] ?? '',
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

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:12',
            'name' => 'sometimes|nullable|string|max:255',
        ], [
            'password.min' => __('alma-auth::messages.password_rejected'),
        ]);

        $accepted = $this->auth->openAccount(
            $data['email'],
            $data['password'],
            (string) ($data['name'] ?? ''),
            $request->ip() ?? '0.0.0.0',
        );

        if (! $accepted) {
            throw ValidationException::withMessages([
                'password' => [__('alma-auth::messages.password_rejected')],
            ]);
        }

        return response()->json(['status' => 'accepted'], 202);
    }

    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|size:6',
            'device_fingerprint' => 'sometimes|string|max:255',
            'trust_device' => 'sometimes|boolean',
            'device_name' => 'sometimes|nullable|string|max:255',
        ]);

        /** @var AuthenticatableUser $user */
        $user = $request->user();

        if (! $this->auth->verifyTwoFactorChallenge($user, $data['code'])) {
            return response()->json(['status' => 'invalid_code'], 401);
        }

        $fingerprint = $data['device_fingerprint'] ?? '';
        if (($data['trust_device'] ?? false) === true) {
            $this->auth->markTrustedDevice($user, $fingerprint, $data['device_name'] ?? null);
        }

        $user->tokens()->where('id', $user->currentAccessToken()?->getKey())->delete();

        return $this->authenticatedResponse(
            $user,
            $fingerprint,
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

    public function passkeyRegisterOptions(Request $request): JsonResponse
    {
        /** @var AuthenticatableUser $user */
        $user = $request->user();
        $payload = $this->auth->beginPasskeyRegistration($user);

        return response()->json([
            'status' => 'ok',
            'challenge_id' => $payload['challenge_id'],
            'publicKey' => $payload['publicKey'],
        ]);
    }

    public function passkeyRegister(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge_id' => 'required|string',
            'credential' => 'required|array',
            'name' => 'sometimes|nullable|string|max:255',
        ]);

        /** @var AuthenticatableUser $user */
        $user = $request->user();
        $passkey = $this->auth->completePasskeyRegistration(
            $user,
            $data['challenge_id'],
            $data['credential'],
            $request->getHost(),
            $data['name'] ?? null,
        );

        if ($passkey === null) {
            return response()->json(['status' => 'passkey_registration_failed'], 422);
        }

        return response()->json([
            'status' => 'passkey_registered',
            'passkey' => [
                'id' => $passkey->id,
                'name' => $passkey->name,
            ],
        ]);
    }

    public function passkeyLoginOptions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'sometimes|nullable|email',
        ]);

        $payload = $this->auth->beginPasskeyLogin($data['email'] ?? null);

        return response()->json([
            'status' => 'ok',
            'challenge_id' => $payload['challenge_id'],
            'publicKey' => $payload['publicKey'],
        ]);
    }

    public function passkeyLogin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge_id' => 'required|string',
            'credential' => 'required|array',
            'device_fingerprint' => 'sometimes|string|max:255',
        ]);

        $result = $this->auth->completePasskeyLogin(
            $data['challenge_id'],
            $data['credential'],
            $request->getHost(),
        );

        if (! $result->success || $result->user === null) {
            return response()->json(['status' => 'passkey_invalid'], 401);
        }

        return $this->authenticatedResponse(
            $result->user,
            $data['device_fingerprint'] ?? '',
            $request->ip(),
        );
    }

    public function listPasskeys(Request $request): JsonResponse
    {
        /** @var AuthenticatableUser $user */
        $user = $request->user();

        return response()->json([
            'status' => 'ok',
            'passkeys' => $this->auth->listPasskeys($user),
        ]);
    }

    public function revokePasskey(Request $request, int $passkey): JsonResponse
    {
        /** @var AuthenticatableUser $user */
        $user = $request->user();

        if (! $this->auth->revokePasskey($user, $passkey)) {
            return response()->json(['status' => 'passkey_not_found'], 404);
        }

        return response()->json(['status' => 'passkey_revoked']);
    }

    public function listTrustedDevices(Request $request): JsonResponse
    {
        /** @var AuthenticatableUser $user */
        $user = $request->user();

        return response()->json([
            'status' => 'ok',
            'devices' => $this->auth->listTrustedDevices($user),
        ]);
    }

    public function revokeTrustedDevice(Request $request, int $device): JsonResponse
    {
        /** @var AuthenticatableUser $user */
        $user = $request->user();

        if (! $this->auth->revokeTrustedDevice($user, $device)) {
            return response()->json(['status' => 'device_not_found'], 404);
        }

        return response()->json(['status' => 'device_revoked']);
    }

    public function recordConsent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'purpose' => 'required|string|max:64',
            'policy_version' => 'required|string|max:64',
        ]);

        /** @var AuthenticatableUser $user */
        $user = $request->user();
        $record = $this->auth->recordConsent(
            $user,
            $data['purpose'],
            $data['policy_version'],
            $request->ip(),
        );

        if ($record === null) {
            return response()->json(['status' => 'invalid_purpose'], 422);
        }

        return response()->json([
            'status' => 'consent_recorded',
            'consent' => [
                'id' => $record->id,
                'purpose' => $record->purpose,
                'policy_version' => $record->policy_version,
            ],
        ]);
    }

    public function listConsents(Request $request): JsonResponse
    {
        /** @var AuthenticatableUser $user */
        $user = $request->user();

        return response()->json([
            'status' => 'ok',
            'consents' => $this->auth->listConsents($user),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8',
        ]);

        /** @var AuthenticatableUser $user */
        $user = $request->user();

        if (! $this->auth->changePassword($user, $data['current_password'], $data['password'])) {
            return response()->json(['status' => 'invalid_credentials'], 401);
        }

        return response()->json(['status' => 'password_changed']);
    }

    public function revokeSessions(Request $request): JsonResponse
    {
        /** @var AuthenticatableUser $user */
        $user = $request->user();
        $this->auth->revokeAllSessions($user, 'manual');

        return response()->json(['status' => 'sessions_revoked']);
    }

    public function listRoles(Request $request): JsonResponse
    {
        /** @var AuthenticatableUser $user */
        $user = $request->user();

        return response()->json([
            'status' => 'ok',
            'roles' => $this->auth->rolesFor($user),
        ]);
    }

    public function oauthLogin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => 'required|string|max:32',
            'access_token' => 'sometimes|string',
            'id_token' => 'sometimes|string',
            'device_fingerprint' => 'sometimes|string|max:255',
        ]);

        if (! isset($data['access_token']) && ! isset($data['id_token'])) {
            return response()->json(['status' => 'oauth_invalid'], 422);
        }

        $result = $this->auth->attemptOAuthLogin(
            $data['provider'],
            [
                'access_token' => $data['access_token'] ?? null,
                'id_token' => $data['id_token'] ?? null,
            ],
            $request->ip() ?? '0.0.0.0',
            $data['device_fingerprint'] ?? '',
        );

        if (! $result->success || $result->user === null) {
            return response()->json(['status' => 'oauth_invalid'], 401);
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
            $data['device_fingerprint'] ?? '',
            $request->ip(),
        );
    }

    public function oauthLink(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => 'required|string|max:32',
            'access_token' => 'sometimes|string',
            'id_token' => 'sometimes|string',
        ]);

        if (! isset($data['access_token']) && ! isset($data['id_token'])) {
            return response()->json(['status' => 'oauth_invalid'], 422);
        }

        /** @var AuthenticatableUser $user */
        $user = $request->user();
        $identity = $this->auth->linkOAuth(
            $user,
            $data['provider'],
            [
                'access_token' => $data['access_token'] ?? null,
                'id_token' => $data['id_token'] ?? null,
            ],
        );

        if ($identity === null) {
            return response()->json(['status' => 'oauth_link_failed'], 422);
        }

        return response()->json([
            'status' => 'oauth_linked',
            'provider' => $identity->provider,
        ]);
    }

    public function oauthUnlink(Request $request, string $provider): JsonResponse
    {
        /** @var AuthenticatableUser $user */
        $user = $request->user();

        if (! $this->auth->unlinkOAuth($user, $provider)) {
            return response()->json(['status' => 'oauth_not_linked'], 404);
        }

        return response()->json(['status' => 'oauth_unlinked']);
    }

    public function listOAuthLinks(Request $request): JsonResponse
    {
        /** @var AuthenticatableUser $user */
        $user = $request->user();

        return response()->json([
            'status' => 'ok',
            'links' => $this->auth->listOAuthLinks($user),
        ]);
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
