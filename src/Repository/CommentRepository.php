<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Contracts\MultipleEagerLoaderInterface;
use App\Entity\Comment;
use App\Entity\Event;
use App\Entity\User;
use App\Entity\UserOAuth;
use App\Manager\PreloadManager;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 *
 * @implements MultipleEagerLoaderInterface<Comment>
 *
 * @method Comment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Comment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Comment[]    findAll()
 * @method Comment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class CommentRepository extends ServiceEntityRepository implements MultipleEagerLoaderInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly PreloadManager $preloadManager,
    ) {
        parent::__construct($registry, Comment::class);
    }

    public function loadAllEager(array $entities, array $context = []): void
    {
        $comments = $entities;
        foreach ($entities as $entity) {
            // Replies fetched along with their comment (findAllByEventQueryBuilder) are rendered with it
            $children = $entity->getChildren();
            if (!$children instanceof PersistentCollection || $children->isInitialized()) {
                foreach ($children as $child) {
                    $comments[] = $child;
                }
            }
        }

        $this->preloadManager->preloadEntities(
            User::class,
            array_map(static fn (Comment $entity) => $entity->getUser()?->getId(), $comments)
        );

        // Each author's picture falls back to their social account's
        $this->preloadManager->preloadEntities(
            UserOAuth::class,
            array_map(static fn (Comment $entity) => $entity->getUser()?->getOAuth()?->getId(), $comments)
        );
    }

    public function findAllByEventQueryBuilder(Event $event): QueryBuilder
    {
        return $this
            ->createQueryBuilder('c')
            ->distinct()
            ->leftJoin('c.children', 'children')
            ->addSelect('children')
            ->leftJoin('children.user', 'childUser')
            ->addSelect('childUser')
            ->join('c.user', 'user')
            ->addSelect('user')
            ->where('c.event = :event AND c.parent IS NULL AND c.approved = true')
            ->setParameter('event', $event)
            ->orderBy('c.createdAt', 'DESC')
            ->addOrderBy('children.createdAt', 'DESC')
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
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    public function findAllAnswersQueryBuilder(Comment $comment): QueryBuilder
    {
        return $this
            ->createQueryBuilder('c')
            ->where('c.parent = :parent AND c.approved = true')
            ->setParameter('parent', $comment)
            ->orderBy('c.createdAt', 'DESC');
    }
}
