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
use App\Api\ApiResource\EventTag;
use App\Repository\EventRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<EventTag>
 */
final readonly class EventTagProvider implements ProviderInterface
{
    private const array ALLOWED_TYPES = ['categories', 'themes'];

    public function __construct(
        private EventRepository $eventRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $type = $uriVariables['type'] ?? null;
        if (!\in_array($type, self::ALLOWED_TYPES, true)) {
            throw new NotFoundHttpException(\sprintf('Invalid tag type "%s". Allowed types: %s', $type, implode(', ', self::ALLOWED_TYPES)));
        }

        $search = trim((string) ($operation->getParameters()?->get('q')?->getValue() ?? ''));
        $search = '' === $search ? null : $search;

        $page = max(1, (int) ($operation->getParameters()?->get('page')?->getValue() ?? 1));
        $itemsPerPage = min(100, max(1, (int) ($operation->getParameters()?->get('itemsPerPage')?->getValue() ?? 20)));

        $offset = ($page - 1) * $itemsPerPage;
        // Fetch one extra to check if there are more results
        $limit = $itemsPerPage + 1;

        $tags = match ($type) {
            'categories' => $this->eventRepository->getDistinctCategories($search, $limit, $offset),
            'themes' => $this->eventRepository->getDistinctThemes($search, $limit, $offset),
        };

        // Check if there are more results
        $hasMore = \count($tags) > $itemsPerPage;
        $paginatedTags = \array_slice($tags, 0, $itemsPerPage);

        $eventTags = array_map(
            static fn (string $tag): EventTag => new EventTag(id: $tag, text: $tag),
            $paginatedTags
        );

        return [
            'member' => $eventTags,
            'pagination' => [
                'more' => $hasMore,
            ],
        ];
    }
}
