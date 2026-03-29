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
use App\Api\ApiResource\UserAutocomplete;
use App\Api\Pagination\ArrayPaginator;
use App\Entity\User;
use App\Repository\UserRepository;

/**
 * @implements ProviderInterface<UserAutocomplete>
 */
final readonly class UserAutocompleteProvider implements ProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private Pagination $pagination,
    ) {
    }

    /**
     * @return iterable<UserAutocomplete>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $term = trim((string) ($context['filters']['q'] ?? ''));
        if ('' === $term) {
            return [];
        }

        $limit = (int) $this->pagination->getLimit($operation, $context);
        $page = (int) $this->pagination->getPage($context);
        $offset = ($page - 1) * $limit;

        $qb = $this->userRepository->createQueryBuilder('u')
            ->where('u.username LIKE :term OR u.email LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('u.username', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $countQb = $this->userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.username LIKE :term OR u.email LIKE :term')
            ->setParameter('term', '%' . $term . '%');

        $total = (int) $countQb->getQuery()->getSingleScalarResult();
        $results = array_map($this->transformUser(...), $qb->getQuery()->getResult());

        /* @var ArrayPaginator<UserAutocomplete> */
        return new ArrayPaginator($results, $total, $page, $limit);
    }

    private function transformUser(User $user): UserAutocomplete
    {
        return new UserAutocomplete(
            username: $user->getUsername(),
            email: $user->getEmail(),
        );
    }
}
