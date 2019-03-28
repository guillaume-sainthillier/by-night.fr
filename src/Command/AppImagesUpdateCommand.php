<?php

namespace App\Command;

use App\Updater\EventUpdater;
use App\Updater\UserUpdater;
use Symfony\Component\Console\Input\InputInterface;
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
            ->setDescription('Mettre à jour les images (events, users) en provenance des réseaux sociaux');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->userUpdater->update();
        $this->eventUpdater->update();
    }
}
