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
use App\Contracts\MultipleEagerLoaderInterface;
use App\Dto\UserDto;
use App\Entity\User;
use App\Entity\UserEvent;
use App\Entity\UserOAuth;
use App\Manager\PreloadManager;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
 * @implements MultipleEagerLoaderInterface<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserLoaderInterface, DtoFindableRepositoryInterface, MultipleEagerLoaderInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly PreloadManager $preloadManager,
    ) {
        parent::__construct($registry, User::class);
    }

    public function loadAllEager(array $entities, array $context = []): void
    {
        $view = $context['view'] ?? null;

        $loadOauth = fn () => $this->preloadManager->preloadEntities(
            UserOAuth::class,
            array_map(static fn (User $entity) => $entity->getOAuth()?->getId(), $entities)
        );

        if ('users:search:list' === $view) {
            $loadOauth();
        }
    }

    public function loadUserByIdentifier(string $identifier): ?UserInterface
    {
        // Each column is unique on its own, not across both: a member whose username is another
        // member's e-mail matched two rows here, and that member could no longer log in
        return $this->findOneBy(['email' => $identifier]) ?? $this->findOneBy(['username' => $identifier]);
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
     * Members whose calendar holds at least one published event ending on or after $from:
     * the profile page lists that calendar, so anyone else has an empty profile.
     *
     * @return iterable<array>
     */
    public function findAllSitemap(DateTimeInterface $from): iterable
    {
        return $this
            ->createQueryBuilder('u')
            ->select('u.id, u.slug, u.updatedAt')
            ->join(UserEvent::class, 'ue', 'WITH', 'ue.user = u')
            ->join('ue.event', 'e')
            ->where('e.endDate >= :from')
            ->andWhere('e.duplicateOf IS NULL')
            ->andWhere('e.draft = false')
            ->andWhere('u.slug IS NOT NULL')
            ->setParameter('from', $from->format('Y-m-d'))
            ->groupBy('u.id, u.slug, u.updatedAt')
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
            ->addSelect('COUNT(u.id) AS nb_events')
            ->join('u.userEvents', 'c')
            ->orderBy('nb_events', 'DESC')
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
    public function findAllByDtos(array $dtos, bool $eager): array
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
