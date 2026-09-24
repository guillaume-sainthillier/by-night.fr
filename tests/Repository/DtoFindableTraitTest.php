<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Repository;

use App\Dto\EventDto;
use App\Repository\DtoFindableTrait;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * SQLite compares a TEXT column with an integer as text, so the MySQL failure (a VARCHAR
 * compared numerically, every "FMA…" id matching 0) cannot be reproduced against the test
 * database: the binding type itself is what is locked here.
 */
final class DtoFindableTraitTest extends TestCase
{
    public function testExternalIdsAreBoundAsStringsWhenTheFirstOneIsNumeric(): void
    {
        $queryBuilder = new QueryBuilder($this->createStub(EntityManagerInterface::class));

        $finder = new class {
            use DtoFindableTrait {
                addDtosToQueryBuilder as public;
            }
        };
        $finder->addDtosToQueryBuilder($queryBuilder, 'e', [
            $this->createEventDto('111293'),
            $this->createEventDto('FMABRE035V52S8P2'),
        ]);

        $parameter = $queryBuilder->getParameter('externalIds_1');
        self::assertNotNull($parameter);
        self::assertSame(ArrayParameterType::STRING, $parameter->getType());
        self::assertSame(['111293', 'FMABRE035V52S8P2'], $parameter->getValue());
    }

    private function createEventDto(string $externalId): EventDto
    {
        $dto = new EventDto();
        $dto->externalId = $externalId;
        $dto->externalOrigin = 'datatourisme';

        return $dto;
    }
}
