<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
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

    public function findAllByEvent(Event $event, $page = 1, $limit = 10)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('a')
            ->from('App:Comment', 'a')
            ->where('a.event = :event AND a.parent IS NULL AND a.approuve = true')
            ->setParameters([':event' => $event])
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    public function findAllByUser(User $user)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('c')
            ->from('App:Comment', 'c')
            ->where('c.user = :user')
            ->setParameters([':user' => $user->getId()])
            ->getQuery()
            ->execute();
    }

    public function findAllReponses(Comment $comment, $page = 1, $limit = 10)
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('a')
            ->from('App:Comment', 'a')
            ->where('a.parent = :parent AND a.approuve = true')
            ->setParameters([':parent' => $comment])
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    public function findNBCommentaires(Event $event)
    {
        return $this->_em->createQueryBuilder()
            ->select('COUNT(a)')
            ->from('App:Comment', 'a')
            ->where('a.event = :event AND a.parent IS NULL AND a.approuve = true')
            ->setParameters([':event' => $event])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findNBReponses(Comment $parent)
    {
        return $this->_em->createQueryBuilder()
            ->select('COUNT(a)')
            ->from('App:Comment', 'a')
            ->where('a.parent = :parent AND a.approuve = true')
            ->setParameters([':parent' => $parent])
            ->getQuery()
            ->getSingleScalarResult();
    }
}
