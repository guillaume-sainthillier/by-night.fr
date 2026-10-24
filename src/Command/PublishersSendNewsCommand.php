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
use Symfony\Component\Mime\Exception\RfcComplianceException;

/**
 * Sends editions of the publishers' news to their audience (App\Enum\PublisherNewsEdition), in the order given: the
 * daily host cron lists them, so the next one starts once the previous one is done. Previews without --send; --to
 * sends them to an address as a member would get them, recording nothing. --limit caps the e-mails of a run, all
 * editions together, under the mail provider's daily cap: a run again sends to the members still without them.
 */
#[AsCommand('app:publishers:send-news', 'Send editions of the publishers\' news to the members they are for')]
final readonly class PublishersSendNewsCommand
{
    public function __construct(
        private PublisherNewsSender $sender,
        private UserRepository $userRepository,
    ) {
    }

    /**
     * @param list<string> $editions
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The editions, in the order they are sent (e.g. "2026-10-retour 2026-10")')]
        array $editions,
        #[Option('Send them (without, the members they are for are only counted)')]
        bool $send = false,
        #[Option('Send them to this address only, as the member of --as would get them')]
        ?string $to = null,
        #[Option('With --to: the e-mail address of the member the preview is for (default: the first recipient)')]
        ?string $as = null,
        #[Option('Send this many e-mails at most, all editions together')]
        ?int $limit = null,
    ): int {
        $news = [];
        foreach ($editions as $value) {
            $edition = PublisherNewsEdition::tryFrom($value);
            if (null === $edition) {
                $io->error(\sprintf('Unknown edition "%s". Editions: %s.', $value, implode(', ', array_map(static fn (PublisherNewsEdition $case): string => $case->value, PublisherNewsEdition::cases()))));

                return Command::INVALID;
            }

            $news[] = $edition;
        }

        $limit = null === $limit ? null : max(0, $limit);
        $batches = [];
        foreach ($news as $edition) {
            $ids = $this->sender->findRecipientIds($edition);
            $io->note(\sprintf('"%s" is for the %s: %d still to send it to.', $edition->getSubject(), $edition->getAudience()->getLabel(), \count($ids)));

            if (null !== $to) {
                if (Command::SUCCESS !== $this->preview($io, $edition, $to, $as, $ids)) {
                    return Command::FAILURE;
                }

                continue;
            }

            if ([] !== $ids) {
                $batches[] = [$edition, $ids];
            }
        }

        if (null !== $to) {
            return Command::SUCCESS;
        }

        $total = array_sum(array_map(static fn (array $batch): int => \count($batch[1]), $batches));
        if (null !== $limit) {
            $total = min($total, $limit);
        }

        if (!$send) {
            $io->success(\sprintf('Dry run: %d e-mail(s) to send. Run again with --send.', $total));

            return Command::SUCCESS;
        }

        // The limit counts the e-mails handed to the mail provider: an invalid address (a legacy one, "First
        // Last@domain") is skipped without taking a place, and skipped again on the next run
        $sent = 0;
        $failures = [];
        $skipped = [];
        $io->progressStart($total);
        foreach ($batches as [$edition, $ids]) {
            foreach ($this->sender->loadRecipients($ids) as $user) {
                if (null !== $limit && $sent + \count($failures) >= $limit) {
                    break 2;
                }

                try {
                    $this->sender->deliver($edition, $user);
                    ++$sent;
                    $io->progressAdvance();
                } catch (RfcComplianceException) {
                    $skipped[] = [$edition->value, $user->getId(), $user->getEmail()];
                } catch (TransportExceptionInterface $e) {
                    $failures[] = [$edition->value, $user->getId(), $user->getEmail(), $e->getMessage()];
                    $io->progressAdvance();
                }
            }
        }

        $io->progressFinish();

        if ([] !== $skipped) {
            $io->note(\sprintf('%d member(s) skipped, their address is invalid.', \count($skipped)));
            $io->table(['Edition', 'Member', 'E-mail'], $skipped);
        }

        if ([] !== $failures) {
            $io->warning(\sprintf('%d e-mail(s) could not be sent: a run again retries them.', \count($failures)));
            $io->table(['Edition', 'Member', 'E-mail', 'Error'], $failures);
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
