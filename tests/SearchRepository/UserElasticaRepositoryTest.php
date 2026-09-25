<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\SearchRepository;

use App\Factory\UserFactory;
use App\SearchRepository\UserElasticaRepository;
use App\Tests\AppKernelTestCase;
use Elastica\Query;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\Paginator\PaginatorAdapterInterface;
use FOS\ElasticaBundle\Serializer\Callback;

/**
 * A member's first and last names are private: the public search (/api/search, /recherche)
 * must not find a member by them, nor send them to the search index.
 */
final class UserElasticaRepositoryTest extends AppKernelTestCase
{
    public function testTheIndexedMemberHoldsNoPrivateName(): void
    {
        $user = UserFactory::createOne(['username' => 'jazzfan', 'firstname' => 'Jeanne', 'lastname' => 'Dupont']);

        /** @var Callback $serializer */
        $serializer = self::getContainer()->get('fos_elastica.index.user.serializer.callback');
        $document = json_decode($serializer->serialize($user), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['id', 'username'], array_keys($document));
    }

    public function testASearchMatchesTheUsernameOnly(): void
    {
        $queries = [];
        $finder = $this->createStub(PaginatedFinderInterface::class);
        $capture = function (Query $query) use (&$queries): PaginatorAdapterInterface {
            $queries[] = $query->toArray();

            return $this->createStub(PaginatorAdapterInterface::class);
        };
        $finder->method('createPaginatorAdapter')->willReturnCallback($capture);
        $finder->method('createHybridPaginatorAdapter')->willReturnCallback($capture);

        $repository = new UserElasticaRepository($finder);
        $repository->findWithSearch('dupont');
        $repository->findWithHighlightsPaginated('dupont');

        [$searchPage, $autocomplete] = $queries;
        self::assertSame(['username'], $searchPage['query']['bool']['filter'][0]['multi_match']['fields']);
        self::assertSame(['username'], $autocomplete['query']['multi_match']['fields']);
        self::assertSame(['username'], array_keys($autocomplete['highlight']['fields']));
    }
}
