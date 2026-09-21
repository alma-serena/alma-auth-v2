<?php

declare(strict_types=1);

namespace Alma\Auth\Services;

use Alma\Auth\Contracts\PasskeyCeremony;
use Alma\Auth\Support\Base64Url;
use Illuminate\Support\Str;

/**
 * Ceremonia determinista para tests. No valida criptografía WebAuthn real.
 * El cliente de prueba debe devolver el challenge emitido y un credential id.
 */
final class FakePasskeyCeremony implements PasskeyCeremony
{
    public function creationOptions(
        string $userHandle,
        string $userName,
        string $displayName,
        array $excludeCredentialIds = [],
    ): array {
        $challenge = Base64Url::encode(random_bytes(32));

        return [
            'challenge' => $challenge,
            'rp' => [
                'id' => (string) config('alma-auth.passkey_rp_id', 'localhost'),
                'name' => (string) config('alma-auth.passkey_rp_name', 'ALMA Auth'),
            ],
            'user' => [
                'id' => Base64Url::encode($userHandle),
                'name' => $userName,
                'displayName' => $displayName,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],
            ],
            'excludeCredentials' => array_map(
                static fn (string $id): array => ['type' => 'public-key', 'id' => $id],
                $excludeCredentialIds,
            ),
            'timeout' => (int) config('alma-auth.passkey_timeout_ms', 60_000),
            'attestation' => 'none',
        ];
    }

    public function verifyAttestation(
        array $clientCredential,
        array $creationOptions,
        string $originHost,
    ): array {
        $this->assertChallenge($clientCredential, $creationOptions['challenge'] ?? '', 'webauthn.create');

        $credentialId = (string) ($clientCredential['id'] ?? '');
        if ($credentialId === '') {
            $credentialId = Base64Url::encode(random_bytes(16));
        }

        foreach ($creationOptions['excludeCredentials'] ?? [] as $excluded) {
            if (($excluded['id'] ?? null) === $credentialId) {
                throw new \RuntimeException('passkey_excluded');
            }
        }

        $userHandle = Base64Url::decode((string) ($creationOptions['user']['id'] ?? ''));

        return [
            'credential_id' => $credentialId,
            'public_key' => 'fake-pk:'.$credentialId,
            'counter' => 0,
            'transports' => ['internal'],
            'aaguid' => '00000000-0000-0000-0000-000000000000',
            'user_handle' => $userHandle,
        ];
    }

    public function requestOptions(array $allowCredentialIds = []): array
    {
        $challenge = Base64Url::encode(random_bytes(32));

        return [
            'challenge' => $challenge,
            'rpId' => (string) config('alma-auth.passkey_rp_id', 'localhost'),
            'allowCredentials' => array_map(
                static fn (string $id): array => ['type' => 'public-key', 'id' => $id],
                $allowCredentialIds,
            ),
            'userVerification' => 'preferred',
            'timeout' => (int) config('alma-auth.passkey_timeout_ms', 60_000),
        ];
    }

    public function verifyAssertion(
        array $clientCredential,
        array $requestOptions,
        array $stored,
        string $originHost,
    ): array {
        $this->assertChallenge($clientCredential, $requestOptions['challenge'] ?? '', 'webauthn.get');

        $credentialId = (string) ($clientCredential['id'] ?? '');
        if ($credentialId === '' || $credentialId !== $stored['credential_id']) {
            throw new \RuntimeException('passkey_invalid');
        }

        $allowed = $requestOptions['allowCredentials'] ?? [];
        if ($allowed !== []) {
            $ids = array_column($allowed, 'id');
            if (! in_array($credentialId, $ids, true)) {
                throw new \RuntimeException('passkey_not_allowed');
            }
        }

        $userHandle = (string) ($clientCredential['response']['userHandle']
            ?? Base64Url::encode($stored['user_handle']));

        return [
            'counter' => ((int) $stored['counter']) + 1,
            'user_handle' => Base64Url::decode($userHandle),
        ];
    }

    /**
     * @param  array<string, mixed>  $clientCredential
     */
    private function assertChallenge(array $clientCredential, string $expectedChallenge, string $type): void
    {
        $clientDataB64 = (string) ($clientCredential['response']['clientDataJSON'] ?? '');
        if ($clientDataB64 === '' || $expectedChallenge === '') {
            throw new \RuntimeException('passkey_invalid_client_data');
        }

        $json = Base64Url::decode($clientDataB64);
        /** @var array{type?: string, challenge?: string} $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (($data['type'] ?? '') !== $type) {
            throw new \RuntimeException('passkey_invalid_type');
        }

        if (! hash_equals($expectedChallenge, (string) ($data['challenge'] ?? ''))) {
            throw new \RuntimeException('passkey_challenge_mismatch');
        }
    }

    /**
     * Helper de tests: arma una respuesta de registro falsa.
     *
     * @return array<string, mixed>
     */
    public static function fakeAttestationResponse(string $challenge, ?string $credentialId = null): array
    {
        $id = $credentialId ?? Base64Url::encode(Str::random(16));

        return [
            'id' => $id,
            'rawId' => $id,
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => Base64Url::encode(json_encode([
                    'type' => 'webauthn.create',
                    'challenge' => $challenge,
                    'origin' => 'http://localhost',
                ], JSON_THROW_ON_ERROR)),
                'attestationObject' => Base64Url::encode('fake-attestation'),
            ],
        ];
    }

    /**
     * Helper de tests: arma una aserción falsa.
     *
     * @return array<string, mixed>
     */
    public static function fakeAssertionResponse(
        string $challenge,
        string $credentialId,
        string $userHandle,
    ): array {
        return [
            'id' => $credentialId,
            'rawId' => $credentialId,
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => Base64Url::encode(json_encode([
                    'type' => 'webauthn.get',
                    'challenge' => $challenge,
                    'origin' => 'http://localhost',
                ], JSON_THROW_ON_ERROR)),
                'authenticatorData' => Base64Url::encode('fake-auth-data'),
                'signature' => Base64Url::encode('fake-signature'),
                'userHandle' => Base64Url::encode($userHandle),
            ],
        ];
    }
}
