<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Entity\Event;
use App\Handler\EventHandler;
use App\Repository\EventRepository;
use App\Utils\Monitor;
use App\Utils\PaginateTrait;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\PagerfantaInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:events:download-images',
    description: 'Download all missing event images',
)]
class EventsDownloadImagesCommand extends Command
{
    use PaginateTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventRepository $eventRepository,
        private EventHandler $eventHandler,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Handling event image download');

        $qb = $this
            ->eventRepository
            ->createSimpleQueryBuilder('e')
            ->where('e.url IS NOT NULL')
            ->andWhere("e.imageSystem.name IS NULL OR e.imageSystem.name = ''")
            ->orderBy('e.id', Criteria::DESC)
        ;

        /** @var PagerfantaInterface<Event> $pagination */
        $pagination = $this->createQueryBuilderPaginator($qb, 1, 50);
        Monitor::createProgressBar($pagination->getNbPages());
        for ($i = 1; $i <= $pagination->getNbPages(); ++$i) {
            $pagination->setCurrentPage($i);

            $events = $pagination->getCurrentPageResults();
            $events = \is_array($events) ? $events : iterator_to_array($events);

            try {
                $this->eventHandler->handleDownloads($events);

                $this->entityManager->flush();
                $this->entityManager->clear();
            } finally {
                $this->eventHandler->reset();
            }

            Monitor::advanceProgressBar();
        }

        Monitor::finishProgressBar();

        return (int) Command::SUCCESS;
    }
}
