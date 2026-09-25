<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security;

use SensitiveParameter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RateLimiter\AbstractRequestRateLimiter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Counts the login attempts per account (the e-mail or username typed), wherever they come
 * from. Symfony's default limiter also counts them per IP address, but behind Cloudflare the
 * address the application sees may be an edge server's, shared by many visitors: its limit
 * would lock them out together. Keyed by an HMAC, so no login is stored as it is.
 */
final class LoginRateLimiter extends AbstractRequestRateLimiter
{
    public function __construct(
        #[Autowire(service: 'limiter.login')]
        private readonly RateLimiterFactoryInterface $limiterFactory,
        #[Autowire('%kernel.secret%')]
        #[SensitiveParameter]
        private readonly string $secret,
    ) {
    }

    protected function getLimiters(Request $request): array
    {
        $login = mb_strtolower($request->attributes->getString(SecurityRequestAttributes::LAST_USERNAME));

        return [$this->limiterFactory->create(hash_hmac('sha256', $login, $this->secret))];
    }
}
