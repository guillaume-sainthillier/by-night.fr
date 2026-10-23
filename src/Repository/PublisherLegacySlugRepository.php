<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\Publisher;
use App\Entity\PublisherLegacySlug;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PublisherLegacySlug>
 *
 * @method PublisherLegacySlug|null find($id, $lockMode = null, $lockVersion = null)
 * @method PublisherLegacySlug|null findOneBy(array $criteria, array $orderBy = null)
 * @method PublisherLegacySlug[]    findAll()
 * @method PublisherLegacySlug[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class PublisherLegacySlugRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublisherLegacySlug::class);
    }

    /**
     * The publisher that took the events of the deleted one this slug named, if any.
     */
    public function findPublisherBySlug(string $slug): ?Publisher
    {
        return $this->findOneBy(['slug' => $slug])?->getPublisher();
    }

    /**
     * The slugs that led to $from lead to $to, with the next flush: a redirect never goes through a deleted publisher.
     * Through the entities, not a DQL update, so that those already loaded say the same.
     */
    public function moveTo(Publisher $from, Publisher $to): void
    {
        foreach ($this->findBy(['publisher' => $from]) as $legacySlug) {
            $legacySlug->setPublisher($to);
        }
    }
}
