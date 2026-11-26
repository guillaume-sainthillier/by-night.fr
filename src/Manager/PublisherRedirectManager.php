<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use App\Entity\Publisher;
use App\Enum\DeletedPageKind;
use App\Exception\RedirectException;
use App\Repository\DeletedPageRepository;
use App\Repository\PublisherLegacySlugRepository;
use App\Repository\PublisherRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The publisher of a URL ("/organisateurs/le-bikini", its calendar, its widget), throwing RedirectException when the
 * slug is the one of a deleted publisher whose events another one took.
 */
final readonly class PublisherRedirectManager
{
    public function __construct(
        private RequestStack $requestStack,
        private UrlGeneratorInterface $router,
        private PublisherRepository $publisherRepository,
        private PublisherLegacySlugRepository $legacySlugRepository,
        private DeletedPageRepository $deletedPageRepository,
    ) {
    }

    /**
     * @param string $route the route of the URL, which the redirect keeps
     *
     * @throws RedirectException     to the same URL of the publisher that took the deleted one's events (301, its
     *                               query string kept)
     * @throws GoneHttpException     when the publisher was deleted with its events
     * @throws NotFoundHttpException when no publisher ever had this slug
     */
    public function getPublisher(string $slug, string $route = 'app_publisher_index'): Publisher
    {
        $publisher = $this->publisherRepository->findOneBy(['slug' => $slug]);
        if (null !== $publisher) {
            return $publisher;
        }

        $heir = $this->legacySlugRepository->findPublisherBySlug($slug);
        if (null !== $heir) {
            $query = $this->requestStack->getCurrentRequest()?->query->all() ?? [];

            throw new RedirectException($this->router->generate($route, [...$query, 'slug' => $heir->getSlug()]));
        }

        if ($this->deletedPageRepository->isDeleted(DeletedPageKind::Publisher, $slug)) {
            throw new GoneHttpException(\sprintf('Publisher "%s" was deleted.', $slug));
        }

        throw new NotFoundHttpException(\sprintf('No publisher "%s".', $slug));
    }
}
