<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security\TwoFactor\Webauthn;

use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\ECDSA\ES256K;
use Cose\Algorithm\Signature\ECDSA\ES384;
use Cose\Algorithm\Signature\ECDSA\ES512;
use Cose\Algorithm\Signature\EdDSA\EdDSA;
use Cose\Algorithm\Signature\RSA\PS256;
use Cose\Algorithm\Signature\RSA\PS384;
use Cose\Algorithm\Signature\RSA\PS512;
use Cose\Algorithm\Signature\RSA\RS256;
use Cose\Algorithm\Signature\RSA\RS384;
use Cose\Algorithm\Signature\RSA\RS512;
use Cose\Algorithms;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRpEntity;

/**
 * The web-auth/webauthn-lib services of the passkey second factor, built once from the site's settings: its serializer
 * (options to the browser, its responses back) and the validators of the registration and login ceremonies.
 *
 * Adapted from jbtronics/2fa-webauthn, which has no Symfony 8 release, without its legacy U2F support. The relying
 * party has no id: the browser and the validators use the host of the request, the same on every environment.
 */
final readonly class WebauthnCeremonies
{
    /** How long the browser waits for the passkey, in milliseconds */
    public const int TIMEOUT = 60_000;

    private const string RP_NAME = 'By Night';

    private SerializerInterface $serializer;

    private AuthenticatorAssertionResponseValidator $assertionResponseValidator;

    private AuthenticatorAttestationResponseValidator $attestationResponseValidator;

    public function __construct()
    {
        // The passkeys are not asked for an attestation: "none" is the only statement they send
        $attestationStatementSupportManager = new AttestationStatementSupportManager([new NoneAttestationStatementSupport()]);
        $this->serializer = new WebauthnSerializerFactory($attestationStatementSupportManager)->create();

        $factory = new CeremonyStepManagerFactory();
        $factory->setAttestationStatementSupportManager($attestationStatementSupportManager);
        $factory->setExtensionOutputCheckerHandler(new ExtensionOutputCheckerHandler());
        $factory->setAlgorithmManager($this->createAlgorithmManager());

        $this->assertionResponseValidator = new AuthenticatorAssertionResponseValidator($factory->requestCeremony());
        $this->attestationResponseValidator = new AuthenticatorAttestationResponseValidator($factory->creationCeremony());
    }

    public function getRpEntity(): PublicKeyCredentialRpEntity
    {
        return PublicKeyCredentialRpEntity::create(self::RP_NAME);
    }

    /**
     * The algorithms a new passkey may use, in the order of preference
     *
     * @return list<PublicKeyCredentialParameters>
     */
    public function getCredentialParameters(): array
    {
        return array_map(
            PublicKeyCredentialParameters::createPk(...),
            [
                Algorithms::COSE_ALGORITHM_ES256,
                Algorithms::COSE_ALGORITHM_EDDSA,
                Algorithms::COSE_ALGORITHM_ES384,
                Algorithms::COSE_ALGORITHM_ES512,
                Algorithms::COSE_ALGORITHM_ES256K,
                Algorithms::COSE_ALGORITHM_RS256,
                Algorithms::COSE_ALGORITHM_RS384,
                Algorithms::COSE_ALGORITHM_RS512,
                Algorithms::COSE_ALGORITHM_PS256,
                Algorithms::COSE_ALGORITHM_PS384,
                Algorithms::COSE_ALGORITHM_PS512,
            ],
        );
    }

    public function getSerializer(): SerializerInterface
    {
        return $this->serializer;
    }

    /**
     * Options as the browser reads them: the binary values in base64url, no null member
     */
    public function toJson(object $options): string
    {
        return $this->serializer->serialize($options, 'json', [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            JsonEncode::OPTIONS => \JSON_THROW_ON_ERROR,
        ]);
    }

    public function getAssertionResponseValidator(): AuthenticatorAssertionResponseValidator
    {
        return $this->assertionResponseValidator;
    }

    public function getAttestationResponseValidator(): AuthenticatorAttestationResponseValidator
    {
        return $this->attestationResponseValidator;
    }

    private function createAlgorithmManager(): Manager
    {
        return Manager::create()->add(
            ES256::create(),
            new EdDSA(),
            ES384::create(),
            ES512::create(),
            ES256K::create(),
            RS256::create(),
            RS384::create(),
            RS512::create(),
            PS256::create(),
            PS384::create(),
            PS512::create(),
        );
    }
}
