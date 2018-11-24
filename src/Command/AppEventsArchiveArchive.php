<?php

namespace App\Command;

use App\Archive\EventArchivator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AppEventsArchiveArchive extends AppCommand
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
            ->setName('app:events:archive')
            ->setDescription('Archive les vieux événements dans ElasticSearch');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->eventArchivator->archive();
    }
}
