<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\ProviderInterface;
use App\Api\Pagination\PagerfantaPaginator;
use App\Entity\Tag;
use App\SearchRepository\TagElasticaRepository;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;

/**
 * @implements ProviderInterface<Tag>
 */
final readonly class TagAutocompleteProvider implements ProviderInterface
{
    public function __construct(
        private RepositoryManagerInterface $repositoryManager,
        private Pagination $pagination,
    ) {
    }

    /**
     * @return iterable<Tag>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $term = trim((string) ($context['filters']['q'] ?? ''));
        if ('' === $term) {
            return [];
        }

        $limit = $this->pagination->getLimit($operation, $context);
        $page = $this->pagination->getPage($context);

        /** @var TagElasticaRepository $repo */
        $repo = $this->repositoryManager->getRepository(Tag::class);
        $results = $repo->findWithSearch($term);

        $results->setMaxPerPage($limit);
        $results->setCurrentPage($page);

        /* @var PagerfantaPaginator<Tag, Tag> */
        return new PagerfantaPaginator($results, static fn (Tag $tag): Tag => $tag);
    }
}
