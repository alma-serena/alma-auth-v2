<?php

declare(strict_types=1);

namespace Alma\Auth\Services;

use Alma\Auth\Contracts\PasskeyCeremony;
use Alma\Auth\Support\Base64Url;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\EmptyTrustPath;

/**
 * Adaptador de producción sobre web-auth/webauthn-lib (^5.3.9).
 */
final class WebAuthnPasskeyCeremony implements PasskeyCeremony
{
    private AttestationStatementSupportManager $attestationManager;

    private CeremonyStepManagerFactory $ceremonyFactory;

    public function __construct()
    {
        $this->attestationManager = AttestationStatementSupportManager::create();
        $this->ceremonyFactory = new CeremonyStepManagerFactory;
        $origins = config('alma-auth.passkey_origins', ['http://localhost']);
        if (! is_array($origins) || $origins === []) {
            $origins = ['http://localhost'];
        }
        $this->ceremonyFactory->setAllowedOrigins(array_values($origins));
    }

    public function creationOptions(
        string $userHandle,
        string $userName,
        string $displayName,
        array $excludeCredentialIds = [],
    ): array {
        $exclude = [];
        foreach ($excludeCredentialIds as $id) {
            $exclude[] = PublicKeyCredentialDescriptor::create(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                Base64Url::decode($id),
            );
        }

        $options = PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create(
                (string) config('alma-auth.passkey_rp_name', 'ALMA Auth'),
                (string) config('alma-auth.passkey_rp_id', 'localhost'),
            ),
            PublicKeyCredentialUserEntity::create($userName, $userHandle, $displayName),
            random_bytes(32),
            [PublicKeyCredentialParameters::createPk(-7)],
            AuthenticatorSelectionCriteria::create(
                authenticatorAttachment: null,
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
            ),
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            $exclude,
            (int) config('alma-auth.passkey_timeout_ms', 60_000),
        );

        return $this->normalize($options);
    }

    public function verifyAttestation(
        array $clientCredential,
        array $creationOptions,
        string $originHost,
    ): array {
        $serializer = $this->serializer();
        $options = $serializer->deserialize(
            json_encode($creationOptions, JSON_THROW_ON_ERROR),
            PublicKeyCredentialCreationOptions::class,
            'json',
        );

        /** @var PublicKeyCredential $credential */
        $credential = $serializer->deserialize(
            json_encode($clientCredential, JSON_THROW_ON_ERROR),
            PublicKeyCredential::class,
            'json',
        );

        $response = $credential->response;
        if (! $response instanceof AuthenticatorAttestationResponse) {
            throw new \RuntimeException('passkey_invalid_attestation');
        }

        $validator = AuthenticatorAttestationResponseValidator::create(
            $this->ceremonyFactory->creationCeremony(),
        );

        $record = $validator->check($response, $options, $originHost);

        return [
            'credential_id' => Base64Url::encode($record->publicKeyCredentialId),
            'public_key' => Base64Url::encode($record->credentialPublicKey),
            'counter' => $record->counter,
            'transports' => $record->transports,
            'aaguid' => $record->aaguid->toRfc4122(),
            'user_handle' => $record->userHandle,
        ];
    }

    public function requestOptions(array $allowCredentialIds = []): array
    {
        $allow = [];
        foreach ($allowCredentialIds as $id) {
            $allow[] = PublicKeyCredentialDescriptor::create(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                Base64Url::decode($id),
            );
        }

        $options = PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            (string) config('alma-auth.passkey_rp_id', 'localhost'),
            $allow,
            PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            (int) config('alma-auth.passkey_timeout_ms', 60_000),
        );

        return $this->normalize($options);
    }

    public function verifyAssertion(
        array $clientCredential,
        array $requestOptions,
        array $stored,
        string $originHost,
    ): array {
        $serializer = $this->serializer();
        $options = $serializer->deserialize(
            json_encode($requestOptions, JSON_THROW_ON_ERROR),
            PublicKeyCredentialRequestOptions::class,
            'json',
        );

        /** @var PublicKeyCredential $credential */
        $credential = $serializer->deserialize(
            json_encode($clientCredential, JSON_THROW_ON_ERROR),
            PublicKeyCredential::class,
            'json',
        );

        $response = $credential->response;
        if (! $response instanceof AuthenticatorAssertionResponse) {
            throw new \RuntimeException('passkey_invalid_assertion');
        }

        $record = CredentialRecord::create(
            Base64Url::decode($stored['credential_id']),
            PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            $stored['transports'] ?? [],
            'none',
            EmptyTrustPath::create(),
            Uuid::fromString($stored['aaguid'] ?? '00000000-0000-0000-0000-000000000000'),
            Base64Url::decode($stored['public_key']),
            $stored['user_handle'],
            (int) $stored['counter'],
        );

        $validator = AuthenticatorAssertionResponseValidator::create(
            $this->ceremonyFactory->requestCeremony(),
        );

        $updated = $validator->check(
            $record,
            $response,
            $options,
            $originHost,
            $stored['user_handle'],
        );

        return [
            'counter' => $updated->counter,
            'user_handle' => $updated->userHandle,
        ];
    }

    private function serializer(): SerializerInterface
    {
        return (new WebauthnSerializerFactory($this->attestationManager))->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(object $options): array
    {
        $json = $this->serializer()->serialize($options, 'json');
        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }
}
