<?php

declare(strict_types=1);

namespace Alma\Auth\Contracts;

/**
 * Abstracción de la ceremonia WebAuthn. El host puede sustituir la
 * implementación; los tests usan FakePasskeyCeremony.
 */
interface PasskeyCeremony
{
    /**
     * @param  list<string>  $excludeCredentialIds  IDs en base64url
     * @return array<string, mixed> Opciones JSON-serializables para credentials.create()
     */
    public function creationOptions(
        string $userHandle,
        string $userName,
        string $displayName,
        array $excludeCredentialIds = [],
    ): array;

    /**
     * @param  array<string, mixed>  $clientCredential  PublicKeyCredential del navegador
     * @param  array<string, mixed>  $creationOptions  Opciones emitidas previamente
     * @return array{
     *     credential_id: string,
     *     public_key: string,
     *     counter: int,
     *     transports: list<string>,
     *     aaguid: string,
     *     user_handle: string
     * }
     */
    public function verifyAttestation(
        array $clientCredential,
        array $creationOptions,
        string $originHost,
    ): array;

    /**
     * @param  list<string>  $allowCredentialIds  IDs en base64url (vacío = discoverable)
     * @return array<string, mixed>
     */
    public function requestOptions(array $allowCredentialIds = []): array;

    /**
     * @param  array<string, mixed>  $clientCredential
     * @param  array<string, mixed>  $requestOptions
     * @param  array{
     *     credential_id: string,
     *     public_key: string,
     *     counter: int,
     *     transports?: list<string>,
     *     aaguid?: string,
     *     user_handle: string
     * }  $stored
     * @return array{counter: int, user_handle: string}
     */
    public function verifyAssertion(
        array $clientCredential,
        array $requestOptions,
        array $stored,
        string $originHost,
    ): array;
}
