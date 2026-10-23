<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security\TwoFactor;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\Http\Authenticator\Passport\Credentials\TwoFactorCodeCredentials;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Throttles the 2FA check paths of every firewall (TOTP, backup and passkey codes all reach
 * scheb's TwoFactorAuthenticator with a TwoFactorCodeCredentials badge):
 *
 * - `two_factor_check_user` (5 / 15 min, keyed by user id, whatever the IP) and
 *   `two_factor_check_ip` (20 / 15 min) are only peeked before the code is checked;
 * - only a failed attempt consumes a token, a successful one resets the per-user limiter;
 * - a throttled attempt gets one generic message (429 for JSON callers) and a warning log
 *   carrying the user id and IP, never the submitted code.
 *
 * Runs before the built-in login throttling (priority 2080), which also sees these attempts
 * (same UserBadge) but is keyed by username + IP only.
 */
final readonly class TwoFactorThrottlingListener
{
    public const string MESSAGE_KEY = 'two_factor_too_many_attempts';

    private const string THROTTLED_ATTRIBUTE = '_two_factor_throttled';

    public function __construct(
        private RateLimiterFactoryInterface $twoFactorCheckUserLimiter,
        private RateLimiterFactoryInterface $twoFactorCheckIpLimiter,
        private RequestStack $requestStack,
        private LoggerInterface $logger,
    ) {
    }

    #[AsEventListener(event: CheckPassportEvent::class, priority: 2090)]
    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $user = $this->getTwoFactorUser($event->getPassport());
        $request = $this->requestStack->getMainRequest();
        if (null === $user || null === $request) {
            return;
        }

        foreach ($this->getLimiters($user, $request) as $limiter) {
            $limit = $limiter->consume(0);
            if (!$limit->isAccepted() || 0 === $limit->getRemainingTokens()) {
                $request->attributes->set(self::THROTTLED_ATTRIBUTE, true);
                $this->logger->warning('2FA check throttled.', [
                    'user_id' => $user->getId(),
                    'ip' => $request->getClientIp(),
                ]);

                throw new CustomUserMessageAuthenticationException(self::MESSAGE_KEY);
            }
        }
    }

    #[AsEventListener(event: LoginFailureEvent::class)]
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $user = $this->getTwoFactorUser($event->getPassport());
        if (null === $user) {
            return;
        }

        $request = $event->getRequest();
        if ($request->attributes->getBoolean(self::THROTTLED_ATTRIBUTE)) {
            if ($request->isXmlHttpRequest() || 'json' === $request->getPreferredFormat()) {
                $event->setResponse(new JsonResponse(
                    ['error' => ['code' => 'TOO_MANY_ATTEMPTS', 'message' => 'Too many attempts, retry later.']],
                    Response::HTTP_TOO_MANY_REQUESTS,
                ));
            }

            return;
        }

        foreach ($this->getLimiters($user, $request) as $limiter) {
            $limiter->consume();
        }
    }

    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $this->getTwoFactorUser($event->getPassport());
        if (null === $user) {
            return;
        }

        $this->twoFactorCheckUserLimiter->create((string) $user->getId())->reset();
    }

    private function getTwoFactorUser(?Passport $passport): ?User
    {
        $badge = $passport?->getBadge(TwoFactorCodeCredentials::class);
        if (!$badge instanceof TwoFactorCodeCredentials) {
            return null;
        }

        $user = $badge->getTwoFactorToken()->getUser();

        return $user instanceof User ? $user : null;
    }

    /**
     * @return iterable<LimiterInterface>
     */
    private function getLimiters(User $user, Request $request): iterable
    {
        yield $this->twoFactorCheckUserLimiter->create((string) $user->getId());
        yield $this->twoFactorCheckIpLimiter->create((string) $request->getClientIp());
    }
}
