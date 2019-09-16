<?php

namespace App\Command;

use App\Archive\EventArchivator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AppEventsArchiveArchive extends Command
{
    private $eventArchivator;

    /**
     * {@inheritdoc}
     */
    public function __construct(EventArchivator $eventArchivator)
    {
        parent::__construct();

        $this->eventArchivator = $eventArchivator;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
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
