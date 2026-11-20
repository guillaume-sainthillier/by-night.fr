<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Doctrine\Query;

use App\Doctrine\Query\IndexHintWalker;
use App\Entity\City;
use App\Entity\Place;
use App\Factory\CityFactory;
use App\Tests\AppKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class IndexHintWalkerTest extends AppKernelTestCase
{
    public function testTheHintNamesTheTableAliasOfTheQuery(): void
    {
        $query = $this->entityManager()
            ->createQueryBuilder()
            ->select('c')
            ->from(City::class, 'c')
            ->where('c.upcomingEvents > 0')
            ->getQuery();

        $sql = IndexHintWalker::useIndex($query, 'c', 'admin_zone_type_latitude_idx')->getSQL();

        self::assertIsString($sql);
        self::assertStringStartsWith('SELECT /*+ INDEX(a0_ admin_zone_type_latitude_idx) */ a0_.', $sql);
        self::assertStringContainsString('FROM admin_zone a0_', $sql);
    }

    public function testEachAliasGetsItsIndex(): void
    {
        $query = $this->entityManager()
            ->createQueryBuilder()
            ->select('DISTINCT p.id')
            ->from(Place::class, 'p')
            ->innerJoin('p.city', 'c')
            ->getQuery();
        IndexHintWalker::useIndex($query, 'p', 'place_idx');
        IndexHintWalker::useIndex($query, 'c', 'city_idx');

        $sql = $query->getSQL();

        self::assertIsString($sql);
        self::assertMatchesRegularExpression('#^SELECT /\*\+ INDEX\((\w+) place_idx\) INDEX\((\w+) city_idx\) \*/ DISTINCT #', $sql);
        preg_match('#INDEX\((\w+) place_idx\) INDEX\((\w+) city_idx\)#', $sql, $aliases);
        self::assertStringContainsString('FROM place ' . $aliases[1], $sql);
        self::assertStringContainsString('admin_zone ' . $aliases[2], $sql);
    }

    public function testTheHintedQueryRunsWhereTheHintIsAComment(): void
    {
        CityFactory::createOne(['name' => 'Albi']);

        $query = $this->entityManager()
            ->createQueryBuilder()
            ->select('c.name')
            ->from(City::class, 'c')
            ->where('c.name = :name')
            ->setParameter('name', 'Albi')
            ->getQuery();

        self::assertSame([['name' => 'Albi']], IndexHintWalker::useIndex($query, 'c', 'admin_zone_type_latitude_idx')->getArrayResult());
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
