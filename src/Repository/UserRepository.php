<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Contracts\DtoFindableRepositoryInterface;
use App\Dto\UserDto;
use App\Entity\User;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @implements DtoFindableRepositoryInterface<UserDto, User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserLoaderInterface, DtoFindableRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function loadUserByIdentifier(string $identifier): ?UserInterface
    {
        return $this
            ->createQueryBuilder('u')
            ->where('u.username = :usernameOrEmail OR u.email = :usernameOrEmail')
            ->setParameter('usernameOrEmail', $identifier)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * @return iterable<array>
     */
    public function findAllSitemap(): iterable
    {
        return $this
            ->createQueryBuilder('u')
            ->select('u.id, u.slug, u.updatedAt')
            ->getQuery()
            ->toIterable();
    }

    public function getUsersWithInfoQueryBuilder(DateTimeInterface $from): QueryBuilder
    {
        return $this
            ->createQueryBuilder('u')
            ->select('u', 'i')
            ->join('u.oAuth', 'i')
            ->where('u.updatedAt >= :from')
            ->andWhere('i.facebook_id IS NOT NULL')
            ->andWhere('u.image.name IS NULL')
            ->setParameter('from', $from->format('Y-m-d'));
    }

    public function findAllTopUsersQueryBuilder(): QueryBuilder
    {
        return $this
            ->createQueryBuilder('u')
            ->select('u')
            ->addSelect('i')
            ->addSelect('COUNT(u.id) AS nb_events')
            ->leftJoin('u.oAuth', 'i')
            ->leftJoin('u.userEvents', 'c')
            ->orderBy('nb_events', Criteria::DESC)
            ->groupBy('u.id');
    }

    public function findOneBySocial(string $email, string $infoPrefix, string $socialId): ?User
    {
        return $this
            ->createQueryBuilder('u')
            ->select('u')
            ->addSelect('i')
            ->leftJoin('u.oAuth', 'i')
            ->where('u.email = :email')
            ->setParameter('email', $email)
            ->orWhere(\sprintf('i.%s_id = :socialId', $infoPrefix))
            ->setParameter('socialId', $socialId)
            ->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

    /**
     * {@inheritDoc}
     */
    public function findAllByDtos(array $dtos): array
    {
        $idsWheres = [];
        foreach ($dtos as $dto) {
            if (null !== $dto->entityId) {
                $idsWheres[$dto->entityId] = true;
            }
        }

        if ([] === $idsWheres) {
            return [];
        }

        return $this
            ->createQueryBuilder('u')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', array_keys($idsWheres))
            ->getQuery()
            ->execute();
    }
}
