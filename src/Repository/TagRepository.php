<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Contracts\BatchResetInterface;
use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 *
 * @method Tag|null find($id, $lockMode = null, $lockVersion = null)
 * @method Tag|null findOneBy(array $criteria, array $orderBy = null)
 * @method Tag[]    findAll()
 * @method Tag[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TagRepository extends ServiceEntityRepository implements BatchResetInterface
{
    /** @var array<string, Tag> In-memory cache of created tags by lowercase name */
    private array $createdTags = [];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    public function batchReset(): void
    {
        $this->createdTags = [];
    }

    /**
     * Find a tag by name or create a new one if it doesn't exist.
     * Uses in-memory cache to return the same Tag instance within a request.
     */
    public function findOrCreateByName(string $name): Tag
    {
        $name = trim($name);
        $cacheKey = mb_strtolower($name);

        // Check in-memory cache first
        if (isset($this->createdTags[$cacheKey])) {
            return $this->createdTags[$cacheKey];
        }

        $tag = $this->findOneByName($name);

        if (null === $tag) {
            $tag = new Tag();
            $tag->setName($name);
            $this->getEntityManager()->persist($tag);
        }

        // Cache for future calls within the same request
        $this->createdTags[$cacheKey] = $tag;

        return $tag;
    }

    /**
     * Find a tag by its exact name (case-insensitive in MySQL).
     */
    public function findOneByName(string $name): ?Tag
    {
        return $this->findOneBy(['name' => trim($name)]);
    }

    /**
     * Find a tag by its slug.
     */
    public function findOneBySlug(string $slug): ?Tag
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Search tags by name with pagination.
     *
     * @return Tag[]
     */
    public function findBySearch(?string $search, int $limit, int $offset): array
    {
        $qb = $this
            ->createQueryBuilder('t')
            ->orderBy('t.name', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if (null !== $search && '' !== $search) {
            $qb
                ->andWhere('t.name LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb
            ->getQuery()
            ->getResult();
    }

    /**
     * Count tags matching a search query.
     */
    public function countBySearch(?string $search): int
    {
        $qb = $this
            ->createQueryBuilder('t')
            ->select('COUNT(t.id)');

        if (null !== $search && '' !== $search) {
            $qb
                ->andWhere('t.name LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return (int) $qb
            ->getQuery()
            ->getSingleScalarResult();
    }
}
