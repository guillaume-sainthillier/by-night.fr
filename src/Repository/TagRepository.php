<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Contracts\DtoFindableRepositoryInterface;
use App\Dto\TagDto;
use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 *
 * @implements DtoFindableRepositoryInterface<TagDto, Tag>
 *
 * @method Tag|null find($id, $lockMode = null, $lockVersion = null)
 * @method Tag|null findOneBy(array $criteria, array $orderBy = null)
 * @method Tag[]    findAll()
 * @method Tag[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TagRepository extends ServiceEntityRepository implements DtoFindableRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
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
     * Find all tags matching the given DTOs by name.
     *
     * @param TagDto[] $dtos
     *
     * @return Tag[]
     */
    public function findAllByDtos(array $dtos, bool $eager): array
    {
        $names = array_filter(array_map(
            static fn (TagDto $dto) => null !== $dto->name ? mb_strtolower(trim($dto->name)) : null,
            $dtos
        ));

        if ([] === $names) {
            return [];
        }

        return $this->createQueryBuilder('t')
            ->where('LOWER(t.name) IN (:names)')
            ->setParameter('names', array_unique($names))
            ->getQuery()
            ->getResult();
    }
}
