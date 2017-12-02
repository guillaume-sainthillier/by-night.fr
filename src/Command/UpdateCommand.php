<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends AppCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('tbn:events:update')
            ->setDescription('Mettre à jour les événements sur By Night');
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
