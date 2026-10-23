<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Enum\PublisherNewsEdition;
use App\Newsletter\PublisherNewsSender;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Sends an edition of the publishers' news to its audience (App\Enum\PublisherNewsEdition), by hand. Previews without
 * --send; --to sends it to an address as a member would get it, recording nothing. --limit splits a run when the
 * mail provider caps the e-mails a day: a run again sends to the members still without it.
 */
#[AsCommand('app:publishers:send-news', 'Send an edition of the publishers\' news to the members it is for')]
final readonly class PublishersSendNewsCommand
{
    public function __construct(
        private PublisherNewsSender $sender,
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The edition (e.g. "2026-10", "2026-10-retour")')]
        string $edition,
        #[Option('Send it (without, the members it is for are only counted)')]
        bool $send = false,
        #[Option('Send it to this address only, as the member of --as would get it')]
        ?string $to = null,
        #[Option('With --to: the e-mail address of the member the preview is for (default: the first recipient)')]
        ?string $as = null,
        #[Option('Send it to this many members at most')]
        ?int $limit = null,
    ): int {
        $news = PublisherNewsEdition::tryFrom($edition);
        if (null === $news) {
            $io->error(\sprintf('Unknown edition "%s". Editions: %s.', $edition, implode(', ', array_map(static fn (PublisherNewsEdition $case): string => $case->value, PublisherNewsEdition::cases()))));

            return Command::INVALID;
        }

        $ids = $this->sender->findRecipientIds($news);
        $io->note(\sprintf('"%s" is for the %s: %d still to send it to.', $news->getSubject(), $news->getAudience()->getLabel(), \count($ids)));

        if (null !== $to) {
            return $this->preview($io, $news, $to, $as, $ids);
        }

        if (null !== $limit) {
            $ids = \array_slice($ids, 0, max(0, $limit));
        }

        if (!$send) {
            $io->success(\sprintf('Dry run: %d e-mail(s) to send. Run again with --send.', \count($ids)));

            return Command::SUCCESS;
        }

        $sent = 0;
        $failures = [];
        foreach ($io->progressIterate($this->sender->loadRecipients($ids), \count($ids)) as $user) {
            try {
                $this->sender->deliver($news, $user);
                ++$sent;
            } catch (TransportExceptionInterface $e) {
                $failures[] = [$user->getId(), $user->getEmail(), $e->getMessage()];
            }
        }

        if ([] !== $failures) {
            $io->warning(\sprintf('%d e-mail(s) could not be sent: a run again retries them.', \count($failures)));
            $io->table(['Member', 'E-mail', 'Error'], $failures);
        }

        $io->success(\sprintf('%d e-mail(s) sent.', $sent));

        return [] === $failures ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @param list<int> $ids
     */
    private function preview(SymfonyStyle $io, PublisherNewsEdition $news, string $to, ?string $as, array $ids): int
    {
        $user = null !== $as
            ? $this->userRepository->findOneBy(['email' => $as])
            : (null !== ($ids[0] ?? null) ? $this->userRepository->find($ids[0]) : null);

        if (null === $user) {
            $io->error(null !== $as ? \sprintf('No member has the address "%s".', $as) : 'No member to preview the edition for: pass --as.');

            return Command::FAILURE;
        }

        $this->sender->send($news, $user, $to);
        $io->success(\sprintf('Preview sent to %s, as %s would get it.', $to, $user->getUsername()));

        return Command::SUCCESS;
    }
}
