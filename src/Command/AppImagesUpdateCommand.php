<?php

namespace App\Command;

use App\Updater\EventUpdater;
use App\Updater\UserUpdater;
use App\Utils\Monitor;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AppImagesUpdateCommand extends AppCommand
{
    /** @var UserUpdater */
    private $userUpdater;

    /** @var EventUpdater */
    private $eventUpdater;

    public function __construct(UserUpdater $userUpdater, EventUpdater $eventUpdater)
    {
        $this->userUpdater = $userUpdater;
        $this->eventUpdater = $eventUpdater;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('app:images:update')
            ->setDescription('Mettre à jour les images (events, users) en provenance des réseaux sociaux')
            ->addArgument('updater', InputArgument::OPTIONAL, 'Le nom de l\'updater (user/event/all)', 'all')
            ->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Date de dernière mise à jour', 'monday this week');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $updater = $input->getArgument('updater');
        $from = new \DateTime($input->getOption('from'));

        if (in_array($updater, ['all', 'user'])) {
            Monitor::writeln("Mise à jour des images <info>utilisateur</info>");
            $this->userUpdater->update($from);
        }

        if (in_array($updater, ['all', 'event'])) {
            Monitor::writeln("Mise à jour des images <info>event</info>");
            $this->eventUpdater->update($from);
        }
    }
}
