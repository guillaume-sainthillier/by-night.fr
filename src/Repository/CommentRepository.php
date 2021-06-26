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

    /**
     * @return Comment[]
     */
    public function findAllByEvent(Event $event, int $page = 1, int $limit = 10): array
    {
        return $this
            ->createQueryBuilder('c')
            ->where('c.event = :event AND c.parent IS NULL AND c.approuve = true')
            ->setParameters([':event' => $event])
            ->orderBy('c.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
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
    public function findAllAnswers(Comment $comment, int $page = 1, int $limit = 10): array
    {
        return $this
            ->createQueryBuilder('c')
            ->where('c.parent = :parent AND c.approuve = true')
            ->setParameters([':parent' => $comment])
            ->orderBy('c.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    public function getCommentsCount(Event $event): int
    {
        return $this
            ->createQueryBuilder('c')
            ->select('COUNT(c)')
            ->where('c.event = :event AND c.parent IS NULL AND c.approuve = true')
            ->setParameters([':event' => $event])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getAnswersCount(Comment $parent): int
    {
        return (int) $this
            ->createQueryBuilder('c')
            ->select('COUNT(a)')
            ->from('App:Comment', 'a')
            ->where('c.parent = :parent AND c.approuve = true')
            ->setParameters([':parent' => $parent])
            ->getQuery()
            ->getSingleScalarResult();
    }
}
