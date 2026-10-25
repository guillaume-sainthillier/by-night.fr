<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\DeletedPage;
use App\Enum\DeletedPageKind;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DeletedPage>
 *
 * @method DeletedPage|null find($id, $lockMode = null, $lockVersion = null)
 * @method DeletedPage|null findOneBy(array $criteria, array $orderBy = null)
 * @method DeletedPage[]    findAll()
 * @method DeletedPage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class DeletedPageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeletedPage::class);
    }

    public function isDeleted(DeletedPageKind $kind, string $identifier): bool
    {
        return null !== $this->findOneBy(['kind' => $kind, 'identifier' => $identifier]);
    }

    /**
     * A venue is named by its location and its slug: "salle-des-fetes" names hundreds of them.
     */
    public static function placeIdentifier(string $locationSlug, string $placeSlug): string
    {
        return $locationSlug . '/' . $placeSlug;
    }
}
