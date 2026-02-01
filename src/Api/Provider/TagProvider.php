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
use ApiPlatform\State\ProviderInterface;
use App\Entity\Tag;
use App\Repository\TagRepository;

/**
 * @implements ProviderInterface<Tag>
 */
final readonly class TagProvider implements ProviderInterface
{
    public function __construct(
        private TagRepository $tagRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $search = trim((string) ($operation->getParameters()?->get('q')?->getValue() ?? ''));
        $search = '' === $search ? null : $search;

        $page = max(1, (int) ($operation->getParameters()?->get('page')?->getValue() ?? 1));
        $itemsPerPage = min(100, max(1, (int) ($operation->getParameters()?->get('itemsPerPage')?->getValue() ?? 20)));

        $offset = ($page - 1) * $itemsPerPage;
        // Fetch one extra to check if there are more results
        $limit = $itemsPerPage + 1;

        $tags = $this->tagRepository->findBySearch($search, $limit, $offset);

        // Check if there are more results
        $hasMore = \count($tags) > $itemsPerPage;
        $paginatedTags = \array_slice($tags, 0, $itemsPerPage);

        return [
            'member' => $paginatedTags,
            'pagination' => [
                'more' => $hasMore,
            ],
        ];
    }
}
