<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command\Oneshot;

use App\Entity\Event;
use App\Handler\ReservationsHandler;
use App\Repository\EventRepository;
use App\Utils\Monitor;
use App\Utils\PaginateTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('app:migrate:contacts')]
class MigrateContactsCommand extends Command
{
    use PaginateTrait;

    public function __construct(private EntityManagerInterface $entityManager, private EventRepository $eventRepository, private ReservationsHandler $reservationsHandler)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queryBuilder = $this->eventRepository
            ->createSimpleQueryBuilder('e')
            ->where('
                e.reservationInternet IS NOT NULL
                OR e.reservationTelephone IS NOT NULL
                OR e.reservationEmail IS NOT NULL
            ');

        $paginator = $this->createQueryBuilderPaginator($queryBuilder, 1, 5_000);
        Monitor::createProgressBar($paginator->getNbPages());
        for ($page = 1; $page <= $paginator->getNbPages(); ++$page) {
            /** @var Event $event */
            foreach ($paginator->getCurrentPageResults() as $event) {
                $infos = array_merge_recursive(
                    $this->reservationsHandler->parseReservations($event->getReservationEmail()),
                    $this->reservationsHandler->parseReservations($event->getReservationInternet()),
                    $this->reservationsHandler->parseReservations($event->getReservationTelephone()),
                );

                foreach ($infos as $key => $values) {
                    if (\is_array($values)) {
                        $infos[$key] = array_values(array_filter($values));
                    }
                }

                $event->setWebsiteContacts($infos['urls'] ?: null);
                $event->setMailContacts($infos['emails'] ?: null);
                $event->setPhoneContacts($infos['phones'] ?: null);
            }

            $this->entityManager->flush();
            $this->entityManager->clear();

            Monitor::advanceProgressBar();
        }

        Monitor::finishProgressBar();

        return Command::SUCCESS;
    }
}
