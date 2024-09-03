<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventSubscriber;

use App\Event\Events;
use App\Event\UserCheckUrlEvent;
use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UserUrlCheckSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly RequestStack $requestStack, private readonly UrlGeneratorInterface $router, private readonly UserRepository $userRepository)
    {
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Events::CHECK_USER_URL => 'onUserCheck',
        ];
    }

    public function onUserCheck(UserCheckUrlEvent $e): void
    {
        // Old route handle
        if (null === $e->getUserId()) {
            $user = $this->userRepository->findOneBy(['username' => $e->getUserUsername()]);
        } else {
            $user = $this->userRepository->find($e->getUserId());
        }

        if (null === $user) {
            throw new NotFoundHttpException(null === $e->getUserId() ? \sprintf('User with username "%s" not found', $e->getUserUsername()) : \sprintf('User with id "%d" not found', $e->getUserId()));
        }

        if (null === $this->requestStack->getParentRequest() && (
            null === $e->getUserId()
            || (null !== $e->getUserSlug() && $user->getSlug() !== $e->getUserSlug())
            || (null !== $e->getUserUsername() && $user->getUserIdentifier() !== $e->getUserUsername())
        )) {
            $routeParams = array_merge([
                'id' => $user->getId(),
                'slug' => $user->getSlug(),
            ], $e->getRouteParams());

            $response = new RedirectResponse(
                $this->router->generate($e->getRouteName(), $routeParams),
                Response::HTTP_MOVED_PERMANENTLY
            );
            $e->setResponse($response);
            $e->stopPropagation();

            return;
        }

        // All is ok :-)
        $e->setUser($user);
    }
}
