<?php

declare(strict_types=1);

namespace Alma\Auth\Services;

use Alma\Auth\Contracts\OAuthIdentityVerifier;
use Alma\Auth\ValueObjects\OAuthUserInfo;
use Illuminate\Support\Facades\Http;

/**
 * Verifica tokens de Google vía APIs públicas (sin Socialite).
 *
 * - access_token → GET oauth2/v3/userinfo
 * - id_token → GET oauth2.googleapis.com/tokeninfo (valida aud = client_id)
 */
final class GoogleOAuthIdentityVerifier implements OAuthIdentityVerifier
{
    public function verify(string $provider, array $credential): OAuthUserInfo
    {
        if (strtolower(trim($provider)) !== 'google') {
            throw new \RuntimeException('oauth_provider_unsupported');
        }

        $clientId = config('alma-auth.oauth_google.client_id');
        if (! is_string($clientId) || $clientId === '') {
            throw new \RuntimeException('oauth_google_client_id_missing');
        }

        $idToken = $credential['id_token'] ?? null;
        $accessToken = $credential['access_token'] ?? null;

        if (is_string($idToken) && $idToken !== '') {
            return $this->fromIdToken($idToken, $clientId);
        }

        if (is_string($accessToken) && $accessToken !== '') {
            return $this->fromAccessToken($accessToken);
        }

        throw new \RuntimeException('oauth_token_missing');
    }

    private function fromAccessToken(string $accessToken): OAuthUserInfo
    {
        $response = Http::timeout(5)
            ->withToken($accessToken)
            ->acceptJson()
            ->get('https://www.googleapis.com/oauth2/v3/userinfo');

        if (! $response->successful()) {
            throw new \RuntimeException('oauth_token_invalid');
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $sub = (string) ($payload['sub'] ?? '');
        if ($sub === '') {
            throw new \RuntimeException('oauth_token_invalid');
        }

        $email = isset($payload['email']) && is_string($payload['email'])
            ? $payload['email']
            : null;

        return new OAuthUserInfo('google', $sub, $email);
    }

    private function fromIdToken(string $idToken, string $clientId): OAuthUserInfo
    {
        $response = Http::timeout(5)
            ->acceptJson()
            ->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('oauth_token_invalid');
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $sub = (string) ($payload['sub'] ?? '');
        if ($sub === '') {
            throw new \RuntimeException('oauth_token_invalid');
        }

        $aud = (string) ($payload['aud'] ?? '');
        $azp = (string) ($payload['azp'] ?? '');
        if ($aud !== $clientId && $azp !== $clientId) {
            throw new \RuntimeException('oauth_token_audience_mismatch');
        }

        if (isset($payload['exp']) && is_numeric($payload['exp']) && (int) $payload['exp'] < time()) {
            throw new \RuntimeException('oauth_token_expired');
        }

        $email = isset($payload['email']) && is_string($payload['email'])
            ? $payload['email']
            : null;

        return new OAuthUserInfo('google', $sub, $email);
    }
}
