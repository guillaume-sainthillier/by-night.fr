<?php

namespace App\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AppImagesUpdateCommand extends AppCommand
{
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
        $updater = $this->getContainer()->get('tbn.user_updater');
        $updater->update();

        $updater = $this->getContainer()->get('tbn.event_updater');
        $updater->update();
    }
}
