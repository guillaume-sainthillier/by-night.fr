<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use App\Entity\Tag;
use App\Exception\RedirectException;
use App\Repository\TagRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class TagRedirectManager
{
    public function __construct(
        private RequestStack $requestStack,
        private UrlGeneratorInterface $router,
        private TagRepository $tagRepository,
    ) {
    }

    /**
     * Get tag entity, throwing RedirectException if URL needs correction.
     *
     * @param int|null $tagId       Tag ID (null for legacy routes)
     * @param string   $tagSlug     Tag slug from URL
     * @param string   $locationSlug Location slug for URL generation
     * @param string   $routeName   Route name to redirect to
     * @param array    $routeParams Additional route parameters
     *
     * @throws RedirectException     when URL needs to be redirected (SEO)
     * @throws NotFoundHttpException when tag is not found
     */
    public function getTag(
        ?int $tagId,
        string $tagSlug,
        string $locationSlug,
        string $routeName,
        array $routeParams = [],
    ): Tag {
        // Legacy route (slug only, no ID)
        if (null === $tagId) {
            $tag = $this->tagRepository->findOneBySlug($tagSlug);
        } else {
            $tag = $this->tagRepository->find($tagId);
        }

        if (null === $tag) {
            throw new NotFoundHttpException(
                null === $tagId
                    ? \sprintf('Tag with slug "%s" not found', $tagSlug)
                    : \sprintf('Tag with id "%d" not found', $tagId)
            );
        }

        // Check for URL mismatch (missing ID or wrong slug)
        // Only redirect if this is not a sub-request (ESI, etc.)
        if (null === $this->requestStack->getParentRequest() && (
            null === $tagId
            || $tag->getSlug() !== $tagSlug
        )) {
            throw new RedirectException($this->router->generate(
                $routeName,
                array_merge([
                    'id' => $tag->getId(),
                    'slug' => $tag->getSlug(),
                    'location' => $locationSlug,
                ], $routeParams)
            ));
        }

        return $tag;
    }
}
