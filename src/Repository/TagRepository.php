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
     * Names the database considers equal on more than one tag, one spelling per
     * group. Its collation decides what "equal" means (case, accents, trailing
     * spaces), exactly like the unique key on tag.name does.
     *
     * @return list<string>
     */
    public function findDuplicateNames(): array
    {
        $names = $this
            ->createQueryBuilder('t')
            ->select('t.name AS name')
            ->groupBy('t.name')
            ->having('COUNT(t.id) > 1')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return array_map(strval(...), $names);
    }

    /**
     * Every tag whose name the database considers equal to the given one, oldest first.
     *
     * @return Tag[]
     */
    public function findAllSharingName(string $name): array
    {
        return $this
            ->createQueryBuilder('t')
            ->where('t.name = :name')
            ->setParameter('name', $name)
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
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
