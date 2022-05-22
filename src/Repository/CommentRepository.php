<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\Event;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Comment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Comment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Comment[]    findAll()
 * @method Comment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    public function findAllByEventQuery(Event $event): Query
    {
        return $this
            ->createQueryBuilder('c')
            ->where('c.event = :event AND c.parent IS NULL AND c.approuve = true')
            ->setParameters([':event' => $event])
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery();
    }

    /**
     * @return Comment[]
     */
    public function findAllByUser(User $user): array
    {
        return $this
            ->createQueryBuilder('c')
            ->where('c.user = :user')
            ->setParameters([':user' => $user->getId()])
            ->getQuery()
            ->execute();
    }

    /**
     * @return Comment[]
     */
    public function findAllAnswersQuery(Comment $comment): Query
    {
        return $this
            ->createQueryBuilder('c')
            ->where('c.parent = :parent AND c.approuve = true')
            ->setParameters([':parent' => $comment])
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery();
    }
}
