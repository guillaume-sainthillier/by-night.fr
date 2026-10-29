<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Comparator\CityComparator;
use App\Entity\CityAlias;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CityAlias>
 *
 * @method CityAlias|null find($id, $lockMode = null, $lockVersion = null)
 * @method CityAlias|null findOneBy(array $criteria, array $orderBy = null)
 * @method CityAlias[]    findAll()
 * @method CityAlias[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class CityAliasRepository extends ServiceEntityRepository
{
    private const int ROWS_PER_INSERT = 500;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CityAlias::class);
    }

    /**
     * The aliases of a country's cities that are this name, as CityComparator::nameKey() gives it ("PARIS 9EME" is
     * "Paris 9ème").
     *
     * @return list<CityAlias> with their city and its parent
     */
    public function findByCountryAndKey(string $countryId, string $nameKey): array
    {
        /* @var list<CityAlias> */
        return $this
            ->createQueryBuilder('a')
            ->addSelect('c', 'p')
            ->join('a.city', 'c')
            // With its parent: an association to the root of the admin_zone inheritance cannot be proxied
            ->leftJoin('c.parent', 'p')
            ->where('c.country = :country')
            ->andWhere('a.nameKey = :nameKey')
            ->setParameter('country', $countryId)
            ->setParameter('nameKey', $nameKey)
            ->getQuery()
            ->getResult();
    }

    /**
     * Replaces the aliases of the cities of these countries in one transaction: a lookup meanwhile reads the former
     * ones.
     *
     * @param list<string>                 $countryIds
     * @param iterable<array{int, string}> $aliases    the city's id and the name
     *
     * @return int the aliases written
     */
    public function replaceAll(array $countryIds, iterable $aliases): int
    {
        $connection = $this->getEntityManager()->getConnection();

        return $connection->transactional(function () use ($connection, $countryIds, $aliases): int {
            $connection->executeStatement(
                'DELETE FROM city_alias WHERE city_id IN (SELECT id FROM admin_zone WHERE country_id IN (?))',
                [$countryIds],
                [ArrayParameterType::STRING],
            );

            $written = 0;
            $rows = [];
            foreach ($aliases as [$cityId, $name]) {
                $rows[] = [$cityId, $name, CityComparator::nameKey($name)];
                if (self::ROWS_PER_INSERT === \count($rows)) {
                    $written += $this->insert($rows);
                    $rows = [];
                }
            }

            $written += $this->insert($rows);

            return $written;
        });
    }

    /**
     * @param list<array{int, string, string}> $rows the city's id, the name and its key
     */
    private function insert(array $rows): int
    {
        if ([] === $rows) {
            return 0;
        }

        return (int) $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO city_alias (city_id, name, name_key) VALUES ' . implode(', ', array_fill(0, \count($rows), '(?, ?, ?)')),
            array_merge(...$rows),
        );
    }
}
