<?php

namespace App\Command;

use App\Archive\EventArchivator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ArchiveCommand extends AppCommand
{
    private $eventArchivator;

    /**
     * {@inheritdoc}
     */
    public function __construct(EventArchivator $eventArchivator)
    {
        $this->eventArchivator = $eventArchivator;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('tbn:events:archive')
            ->setDescription('Archive les vieux événements sur By Night');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->eventArchivator->archive();
    }
}
