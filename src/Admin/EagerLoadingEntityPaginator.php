<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Admin;

use App\Contracts\MultipleEagerLoaderInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Orm\EntityPaginatorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\PaginatorDto;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Hands the page of an index to its repository's loadAllEager() (view "admin:index") before
 * EasyAdmin renders the columns, so each association is loaded once for the whole page.
 *
 * Joins in createIndexQueryBuilder() would do it too, but a joined collection repeats every row
 * of the page query, and any join turns EasyAdmin's count into a COUNT(DISTINCT) over it.
 *
 * paginate() returns EasyAdmin's paginator itself: the other methods only fulfil the interface.
 */
#[AsDecorator(EntityPaginatorInterface::class)]
final readonly class EagerLoadingEntityPaginator implements EntityPaginatorInterface
{
    public function __construct(
        #[AutowireDecorated]
        private EntityPaginatorInterface $inner,
        private ManagerRegistry $registry,
        private AdminContextProviderInterface $adminContextProvider,
    ) {
    }

    public function paginate(PaginatorDto $paginatorDto, QueryBuilder $queryBuilder): EntityPaginatorInterface
    {
        $paginator = $this->inner->paginate($paginatorDto, $queryBuilder);

        // Autocompletes paginate too, and only render each entity's name
        if (Action::INDEX !== $this->adminContextProvider->getContext()?->getCrud()?->getCurrentAction()) {
            return $paginator;
        }

        $entityClass = $queryBuilder->getRootEntities()[0];
        $repository = $this->registry->getRepository($entityClass);
        $entities = iterator_to_array($paginator->getResults() ?? [], false);
        if (
            $repository instanceof MultipleEagerLoaderInterface
            && [] !== $entities
            // A query selecting scalars next to the entity returns rows, not entities
            && [] === array_filter($entities, static fn (mixed $entity): bool => !$entity instanceof $entityClass)
        ) {
            $repository->loadAllEager($entities, ['view' => 'admin:index']);
        }

        return $paginator;
    }

    public function generateUrlForPage(int $page): string
    {
        return $this->inner->generateUrlForPage($page);
    }

    public function getCurrentPage(): int
    {
        return $this->inner->getCurrentPage();
    }

    public function getLastPage(): int
    {
        return $this->inner->getLastPage();
    }

    public function getPageRange(?int $pagesOnEachSide = null, ?int $pagesOnEdges = null): iterable
    {
        return $this->inner->getPageRange($pagesOnEachSide, $pagesOnEdges);
    }

    public function getPageSize(): int
    {
        return $this->inner->getPageSize();
    }

    public function hasPreviousPage(): bool
    {
        return $this->inner->hasPreviousPage();
    }

    public function getPreviousPage(): int
    {
        return $this->inner->getPreviousPage();
    }

    public function hasNextPage(): bool
    {
        return $this->inner->hasNextPage();
    }

    public function getNextPage(): int
    {
        return $this->inner->getNextPage();
    }

    public function hasToPaginate(): bool
    {
        return $this->inner->hasToPaginate();
    }

    public function isOutOfRange(): bool
    {
        return $this->inner->isOutOfRange();
    }

    public function getNumResults(): int
    {
        return $this->inner->getNumResults();
    }

    public function getResults(): ?iterable
    {
        return $this->inner->getResults();
    }

    public function getResultsAsJson(mixed ...$arguments): string
    {
        return $this->inner->getResultsAsJson(...$arguments);
    }
}
