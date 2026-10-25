<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use App\Entity\User;
use App\Enum\DeletedPageKind;
use App\Exception\RedirectException;
use App\Repository\DeletedPageRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class UserRedirectManager
{
    public function __construct(
        private RequestStack $requestStack,
        private UrlGeneratorInterface $router,
        private UserRepository $userRepository,
        private DeletedPageRepository $deletedPageRepository,
    ) {
    }

    /**
     * Get user entity, throwing RedirectException if URL needs correction.
     *
     * @throws RedirectException     when URL needs to be redirected (SEO)
     * @throws GoneHttpException     when the member's account was deleted
     * @throws NotFoundHttpException when user is not found
     */
    public function getUser(
        ?int $userId,
        ?string $userSlug,
        ?string $userUsername,
        string $routeName,
        array $routeParams = [],
    ): User {
        // Old route handle
        if (null === $userId) {
            $user = $this->userRepository->findOneBy(['username' => $userUsername]);
        } else {
            $user = $this->userRepository->find($userId);
        }

        if (null === $user) {
            if (null !== $userId && $this->deletedPageRepository->isDeleted(DeletedPageKind::User, (string) $userId)) {
                throw new GoneHttpException(\sprintf('User with id "%d" was deleted', $userId));
            }

            throw new NotFoundHttpException(null === $userId ? \sprintf('User with username "%s" not found', $userUsername) : \sprintf('User with id "%d" not found', $userId));
        }

        // Check for URL mismatch (wrong slug or username)
        if (null === $this->requestStack->getParentRequest() && (
            null === $userId
            || (null !== $userSlug && $user->getSlug() !== $userSlug)
            || (null !== $userUsername && $user->getUserIdentifier() !== $userUsername)
        )) {
            throw new RedirectException($this->router->generate($routeName, array_merge(['id' => $user->getId(), 'slug' => $user->getSlug()], $routeParams)));
        }

        return $user;
    }
}
