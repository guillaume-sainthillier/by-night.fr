<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use AppBundle\Utils\Monitor;

class CountryCommand extends AppCommand
{
    protected function configure()
    {
        $this
            ->setName('tbn:country:add')
            ->setDescription('Ajoute un pays sur By Night')
            ->addArgument('id', InputArgument::REQUIRED)
            ->addArgument('name', InputArgument::OPTIONAL)
            ->addArgument('capital', InputArgument::OPTIONAL)
            ->addArgument('locale', InputArgument::OPTIONAL)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        Monitor::$output = $output;
        $importer        = $this->getContainer()->get('app.importer.country_importer');

        $importer->import(
            $input->getArgument('id'),
            $input->getArgument('name'),
            $input->getArgument('capital'),
            $input->getArgument('locale')
        );
    }
}
