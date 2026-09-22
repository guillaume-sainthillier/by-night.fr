<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Contracts\ParserInterface;
use App\Repository\ParserStateRepository;
use App\Utils\Monitor;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;

#[AsCommand('app:events:import', 'Ajouter / mettre à jour des nouveaux événements')]
final class EventsImportCommand extends Command
{
    /**
     * @param iterable<ParserInterface> $parsers
     */
    public function __construct(
        #[AutowireIterator(ParserInterface::class)]
        private readonly iterable $parsers,
        private readonly ParserStateRepository $parserStates,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->addArgument('parser', InputArgument::OPTIONAL, 'Nom du parser à lancer', 'all')
            ->addOption('full', 'f', InputOption::VALUE_NONE, 'Effectue un full import du catalogue disponible au lieu des seuls changements depuis le dernier import');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $parserName = $input->getArgument('parser');
        $full = (bool) $input->getOption('full');
        $failed = false;

        foreach ($this->parsers as $parser) {
            if ('all' !== $parserName && $parser->getCommandName() !== $parserName) {
                continue;
            }

            if (!$parser->isEnabled()) {
                if ('all' !== $parserName) {
                    Monitor::writeln(\sprintf(
                        '<warning>%s is not enabled</warning>',
                        $parser->getName()
                    ));
                }

                continue;
            }

            // The watermark is the run *start*: whatever the source changes while we
            // are fetching is picked up by the next run rather than lost in between.
            $startedAt = new DateTimeImmutable();
            $since = $full ? null : $this->parserStates->findLastParsedAt($parser->getCommandName());

            Monitor::writeln(\sprintf(
                'Starting <info>%s</info> (%s)',
                $parser->getName(),
                null === $since ? 'full import' : \sprintf('changes since %s', $since->format('Y-m-d H:i:s'))
            ));

            try {
                $parser->parse($since);
            } catch (Throwable $exception) {
                // One source failing must not keep the next ones from importing: "all" goes on and
                // ends as a failure, while a single parser's failure surfaces as it is
                if ('all' !== $parserName) {
                    throw $exception;
                }

                $failed = true;
                $this->logger->error(\sprintf('%s failed: %s', $parser->getName(), $exception->getMessage()), ['exception' => $exception]);
                Monitor::writeln(\sprintf('<error>%s failed: %s</error>', $parser->getName(), $exception->getMessage()));

                continue;
            }

            // Only a run that completed moves the watermark: after a failure the next run
            // re-fetches from the previous one, and the dedup gate absorbs the overlap.
            $this->parserStates->markParsed($parser->getCommandName(), $startedAt);

            Monitor::writeln(\sprintf(
                '<info>%d</info> enqueued events, <info>%d</info> skipped (unchanged)',
                $parser->getParsedEvents(),
                $parser->getSkippedEvents()
            ));
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
