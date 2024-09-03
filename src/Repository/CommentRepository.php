<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\Event;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 * @method Comment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Comment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Comment[]    findAll()
 * @method Comment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    public function findAllByEventQueryBuilder(Event $event): QueryBuilder
    {
        return $this
            ->createQueryBuilder('c')
            ->where('c.event = :event AND c.parent IS NULL AND c.approved = true')
            ->setParameters([':event' => $event])
            ->orderBy('c.createdAt', Criteria::DESC)
        ;
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

    public function findAllAnswersQueryBuilder(Comment $comment): QueryBuilder
    {
        return $this
            ->createQueryBuilder('c')
            ->where('c.parent = :parent AND c.approved = true')
            ->setParameters([':parent' => $comment])
            ->orderBy('c.createdAt', Criteria::DESC);
    }
}
