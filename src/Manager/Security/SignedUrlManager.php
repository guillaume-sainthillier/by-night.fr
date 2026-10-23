<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager\Security;

use DateInterval;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Signed links mailed to users, signed by Symfony's UriSigner (kernel secret) and expiring after LIFETIME. A link also
 * carries a fingerprint of the state it was issued for: the signature covers it, and isValid() compares it with the
 * current state, so changing that state kills every link issued before. Nothing is stored.
 */
final readonly class SignedUrlManager
{
    public const string LIFETIME = 'PT1H';

    private const string STATE_PARAMETER = '_state';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private UriSigner $uriSigner,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters route parameters
     * @param list<mixed>          $state      values that must not change while the link lives
     */
    public function generate(string $route, array $parameters, array $state): string
    {
        $url = $this->urlGenerator->generate($route, [
            ...$parameters,
            self::STATE_PARAMETER => $this->fingerprint($state),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->uriSigner->sign($url, new DateInterval(self::LIFETIME));
    }

    /**
     * @param list<mixed> $state the same values as given to generate(), read again now
     */
    public function isValid(Request $request, array $state): bool
    {
        return $this->uriSigner->checkRequest($request)
            && hash_equals($this->fingerprint($state), $request->query->getString(self::STATE_PARAMETER));
    }

    /**
     * A hash, so that neither addresses nor secrets end up in the URL (and the access logs)
     *
     * @param list<mixed> $state
     */
    private function fingerprint(array $state): string
    {
        return hash('sha256', json_encode($state, \JSON_THROW_ON_ERROR));
    }
}
