<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Elasticsearch\Pager;

use App\Elasticsearch\RegisterListenersService;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Provider\PagerInterface;
use FOS\ElasticaBundle\Provider\PagerProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Pages the event index by blocks of ids, newest first (see IdRangePager).
 *
 * FOSElastica's ORM provider pages with Doctrine's paginator: for each 5000-event page of the
 * 2.15M events, a DISTINCT subquery over the whole table (twice) and two COUNTs, ~17 s of SQL.
 */
#[AutoconfigureTag('fos_elastica.pager_provider', ['index' => self::INDEX_NAME])]
final readonly class EventPagerProvider implements PagerProviderInterface
{
    public const string INDEX_NAME = 'event';

    public function __construct(
        private EventRepository $eventRepository,
        private EntityManagerInterface $entityManager,
        private RegisterListenersService $registerListenersService,
        private IdRangeCeilings $idRangeCeilings,
    ) {
    }

    public function provide(array $options = []): PagerInterface
    {
        $pager = new IdRangePager(
            $this->eventRepository->createIsActiveQueryBuilder(),
            $this->idRangeCeilings->get(self::INDEX_NAME, $this->eventRepository->findMaxId(...)),
        );

        $this->registerListenersService->register($this->entityManager, $pager, $options);

        return $pager;
    }
}
