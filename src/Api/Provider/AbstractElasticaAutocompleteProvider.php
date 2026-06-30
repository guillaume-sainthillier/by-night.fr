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
use Closure;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Pagerfanta\PagerfantaInterface;

/**
 * Shared "?q=..." autocomplete flow over an Elastica repository: trim the term,
 * short-circuit on an empty term, page the Pagerfanta results, and optionally map
 * each hit to an output DTO. Subclasses only describe their search and (when the
 * raw entity isn't the API output) how to transform a hit.
 *
 * @template TOutput of object
 *
 * @implements ProviderInterface<TOutput>
 */
abstract readonly class AbstractElasticaAutocompleteProvider implements ProviderInterface
{
    public function __construct(
        protected RepositoryManagerInterface $repositoryManager,
        private Pagination $pagination,
    ) {
    }

    /**
     * @return iterable<TOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $term = trim((string) ($context['filters']['q'] ?? ''));
        if ('' === $term) {
            return [];
        }

        $results = $this->search($term);
        $results->setMaxPerPage($this->pagination->getLimit($operation, $context));
        $results->setCurrentPage($this->pagination->getPage($context));

        return new PagerfantaPaginator($results, $this->transformer());
    }

    /**
     * @return PagerfantaInterface<object>
     */
    abstract protected function search(string $term): PagerfantaInterface;

    /**
     * Optional hit → output mapping. Null means the raw entity is the API output.
     *
     * @return (Closure(object): TOutput)|null
     */
    protected function transformer(): ?Closure
    {
        return null;
    }
}
