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
use App\Entity\Event;
use App\Entity\User;
use App\Entity\UserEvent;
use App\Entity\UserOAuth;
use App\Manager\PreloadManager;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Silarhi\CursorPagination\Configuration\OrderConfiguration;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;
use Silarhi\CursorPagination\Pagination\CursorPagination;
use SortDirection;
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

    /**
     * The member whose picture, their own or their network's, is stored under that file name.
     */
    public function findOneByImageName(string $name): ?User
    {
        /* @var User|null */
        return $this
            ->createQueryBuilder('u')
            ->where('u.image.name = :name OR u.imageSystem.name = :name')
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
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
     * @return CursorPagination<array{id: int, slug: string, updatedAt: ?DateTimeImmutable}>
     */
    public function findAllSitemap(DateTimeInterface $from, int $batchSize): CursorPagination
    {
        $queryBuilder = $this
            ->createQueryBuilder('u')
            ->select('u.id, u.slug, u.updatedAt')
            ->join(UserEvent::class, 'ue', Join::ON, 'ue.user = u')
            ->join('ue.event', 'e')
            ->where('e.endDate >= :from')
            ->andWhere('e.duplicateOf IS NULL')
            ->andWhere('e.draft = false')
            ->andWhere('u.slug IS NOT NULL')
            ->setParameter('from', $from->format('Y-m-d'))
            ->groupBy('u.id, u.slug, u.updatedAt');

        return new CursorPagination(
            $queryBuilder,
            new OrderConfigurations(new OrderConfiguration('u.id', static fn (array $user): int => $user['id'])),
            $batchSize,
            fetchJoinCollection: false,
        );
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

    /**
     * The members who joined last and have a picture (uploaded, or taken from a social network), newest first:
     * the faces of the sign-up page.
     *
     * @return User[]
     */
    public function findLatestWithPicture(int $limit): array
    {
        return $this
            ->createQueryBuilder('u')
            ->where('u.image.name IS NOT NULL OR u.imageSystem.name IS NOT NULL')
            ->orderBy('u.id', SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * The members who said they go to an event, the last to say so first: the faces beside its "J'y vais" button.
     *
     * @return User[]
     */
    public function findEventParticipants(Event $event, int $limit): array
    {
        return $this
            ->createQueryBuilder('u')
            ->join('u.userEvents', 'ue')
            ->where('ue.event = :event')
            ->andWhere('ue.going = true')
            ->setParameter('event', $event->getId())
            ->orderBy('ue.id', SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * The name, or the name followed by the first free number ("Camille Martin-2"): the username is unique.
     * getUserIdentifier() is the e-mail, not the username.
     */
    public function getFreeUsername(string $name): string
    {
        $username = $name;
        for ($i = 1; null !== $this->findOneBy(['username' => $username]); ++$i) {
            $username = \sprintf('%s-%d', $name, $i);
        }

        return $username;
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
