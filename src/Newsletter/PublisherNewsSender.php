<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Newsletter;

use App\Entity\PublisherNewsDelivery;
use App\Entity\User;
use App\Enum\PublisherNewsAudience;
use App\Enum\PublisherNewsEdition;
use App\Manager\MailerManager;
use App\Repository\PublisherRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sends an edition of the publishers' news (App\Enum\PublisherNewsEdition) to its audience, a member at a time, each
 * delivery recorded as soon as the e-mail left: a run again goes on where the previous one stopped.
 */
final readonly class PublisherNewsSender
{
    private const int BATCH_SIZE = 50;

    public function __construct(
        private UserRepository $userRepository,
        private PublisherRepository $publisherRepository,
        private MailerManager $mailerManager,
        private EntityManagerInterface $entityManager,
        private UriSigner $uriSigner,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @return list<int> the ids of the members the edition is still to be sent to
     */
    public function findRecipientIds(PublisherNewsEdition $edition): array
    {
        return $this->userRepository->findPublisherNewsRecipientIds(
            $edition,
            new DateTimeImmutable('-' . PublisherNewsAudience::DORMANT_AFTER),
        );
    }

    /**
     * The members of these ids, read by batches: the entity manager is cleared after each one.
     *
     * @param list<int> $ids
     *
     * @return Generator<User>
     */
    public function loadRecipients(array $ids): Generator
    {
        foreach (array_chunk($ids, self::BATCH_SIZE) as $batch) {
            yield from $this->userRepository->findBy(['id' => $batch], ['id' => 'ASC']);
            $this->entityManager->clear();
        }
    }

    /**
     * Sends the edition to the member, then records it. Nothing is recorded when the e-mail could not leave.
     *
     * @throws TransportExceptionInterface
     */
    public function deliver(PublisherNewsEdition $edition, User $user): void
    {
        $this->send($edition, $user);

        $this->entityManager->persist(new PublisherNewsDelivery($user, $edition));
        $this->entityManager->flush();
    }

    /**
     * Sends the edition as the member would get it, recording nothing: to another address, it is a preview.
     *
     * @throws TransportExceptionInterface
     */
    public function send(PublisherNewsEdition $edition, User $user, ?string $to = null): void
    {
        $this->mailerManager->sendPublisherNews(
            $user,
            $this->publisherRepository->findOneByOwner($user),
            $edition,
            $this->getUnsubscribeUrl($user),
            $to,
        );
    }

    /**
     * Signed, so that it needs no login, and never expires: an old e-mail still unsubscribes.
     */
    public function getUnsubscribeUrl(User $user): string
    {
        return $this->uriSigner->sign($this->urlGenerator->generate(
            'app_publisher_news_unsubscribe',
            ['id' => $user->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        ));
    }
}
