<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;

final readonly class DoctrineManager
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $entityName
     */
    public function getEntityManagerForClass(string $entityName): EntityManagerInterface
    {
        $em = $this->managerRegistry->getManagerForClass($entityName);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException(\sprintf('The entity "%s" does not have an associated EntityManager.', $entityName));
        }

        return $em;
    }
}
