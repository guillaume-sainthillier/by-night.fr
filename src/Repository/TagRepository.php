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
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\String\Slugger\AsciiSlugger;

use function Symfony\Component\String\u;

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
        $names = [];
        $slugs = [];
        foreach ($dtos as $dto) {
            if (null === $dto->name || '' === trim($dto->name)) {
                continue;
            }

            $names[mb_strtolower(trim($dto->name))] = true;
            $slugs[self::slugify(trim($dto->name))] = true;
        }

        if ([] === $names) {
            return [];
        }

        // By slug too: the names the unique key on tag.name holds equal ("Théâtre",
        // "THEATRE") share their slug, whatever the database compares names with. The
        // provider then keeps, among these candidates, the tag whose name is the DTO's
        // (TagDto::getUniqueKey()).
        return $this->createQueryBuilder('t')
            ->where('LOWER(t.name) IN (:names)')
            ->orWhere('t.slug IN (:slugs)')
            ->setParameter('names', array_map(strval(...), array_keys($names)), ArrayParameterType::STRING)
            ->setParameter('slugs', array_map(strval(...), array_keys($slugs)), ArrayParameterType::STRING)
            ->getQuery()
            ->getResult();
    }

    /**
     * The slug Gedmo gives a tag of this name (SluggableListener's default transliterator
     * and urlizer).
     */
    private static function slugify(string $name): string
    {
        return new AsciiSlugger()->slug(u($name)->ascii()->toString(), '-')->lower()->toString();
    }
}
