<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Security\TwoFactor;

use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\TextStringObject;
use CBOR\UnsignedIntegerObject;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * A passkey in software, as a browser and its authenticator answer the ceremonies of App\Security\TwoFactor\Webauthn:
 * an ES256 key pair, a "none" attestation on registration, a real signature on each login. The options are the JSON of
 * the data-webauthn-options attribute of the page.
 */
final class SoftwarePasskey
{
    public readonly string $credentialId;

    private readonly OpenSSLAsymmetricKey $privateKey;

    private int $counter = 0;

    public function __construct(private readonly string $origin = 'https://by-night.test')
    {
        $privateKey = openssl_pkey_new(['private_key_type' => \OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        if (false === $privateKey) {
            throw new RuntimeException('Could not create the key pair of the passkey.');
        }

        $this->privateKey = $privateKey;
        $this->credentialId = random_bytes(16);
    }

    /**
     * The answer of navigator.credentials.create(), as assets/js/listeners/webauthn.js posts it
     */
    public function register(string $optionsJson): string
    {
        $options = json_decode($optionsJson, true, flags: \JSON_THROW_ON_ERROR);
        $clientDataJson = $this->clientDataJson('webauthn.create', $options['challenge']);

        // User present (0x01), verified (0x04), attested credential data (0x40)
        $authData = $this->rpIdHash($options['rp']['id'] ?? null) . \chr(0x45) . pack('N', $this->counter)
            . str_repeat("\0", 16) . pack('n', \strlen($this->credentialId)) . $this->credentialId . $this->coseKey();

        $attestationObject = MapObject::create()
            ->add(TextStringObject::create('fmt'), TextStringObject::create('none'))
            ->add(TextStringObject::create('attStmt'), MapObject::create())
            ->add(TextStringObject::create('authData'), ByteStringObject::create($authData));

        return $this->credential([
            'clientDataJSON' => $this->base64Url($clientDataJson),
            'attestationObject' => $this->base64Url((string) $attestationObject),
            'transports' => ['internal'],
        ]);
    }

    /**
     * The answer of navigator.credentials.get(), signed over the options' challenge
     */
    public function authenticate(string $optionsJson, string $userHandle): string
    {
        $options = json_decode($optionsJson, true, flags: \JSON_THROW_ON_ERROR);
        $clientDataJson = $this->clientDataJson('webauthn.get', $options['challenge']);

        // User present (0x01), verified (0x04)
        $authData = $this->rpIdHash($options['rpId'] ?? null) . \chr(0x05) . pack('N', ++$this->counter);
        openssl_sign($authData . hash('sha256', $clientDataJson, true), $signature, $this->privateKey, \OPENSSL_ALGO_SHA256);

        return $this->credential([
            'clientDataJSON' => $this->base64Url($clientDataJson),
            'authenticatorData' => $this->base64Url($authData),
            'signature' => $this->base64Url($signature),
            'userHandle' => $this->base64Url($userHandle),
        ]);
    }

    private function clientDataJson(string $type, string $challenge): string
    {
        return json_encode(['type' => $type, 'challenge' => $challenge, 'origin' => $this->origin], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }

    private function rpIdHash(?string $rpId): string
    {
        return hash('sha256', $rpId ?? (string) parse_url($this->origin, \PHP_URL_HOST), true);
    }

    /**
     * The public key in COSE: EC2 (1: 2), ES256 (3: -7), P-256 (-1: 1), x (-2) and y (-3), each 32 bytes (OpenSSL
     * drops their leading zeros)
     */
    private function coseKey(): string
    {
        $details = openssl_pkey_get_details($this->privateKey);
        if (false === $details) {
            throw new RuntimeException('Could not read the public key of the passkey.');
        }

        return (string) MapObject::create()
            ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2))
            ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(-7))
            ->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1))
            ->add(NegativeIntegerObject::create(-2), ByteStringObject::create(str_pad($details['ec']['x'], 32, "\0", \STR_PAD_LEFT)))
            ->add(NegativeIntegerObject::create(-3), ByteStringObject::create(str_pad($details['ec']['y'], 32, "\0", \STR_PAD_LEFT)));
    }

    /**
     * @param array<string, mixed> $response
     */
    private function credential(array $response): string
    {
        return json_encode([
            'id' => $this->base64Url($this->credentialId),
            'rawId' => $this->base64Url($this->credentialId),
            'type' => 'public-key',
            'response' => $response,
        ], \JSON_THROW_ON_ERROR);
    }

    private function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
