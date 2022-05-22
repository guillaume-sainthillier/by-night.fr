<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Contracts\ParserInterface;
use App\Utils\Monitor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EventsImportCommand extends Command
{
    protected static $defaultName = 'app:events:import';

    /**
     * @param iterable<ParserInterface> $parsers
     */
    public function __construct(private iterable $parsers)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    protected function configure()
    {
        $this->setDescription('Ajouter / mettre à jour des nouveaux événements')
            ->addArgument('parser', InputArgument::OPTIONAL, 'Nom du parser à lancer', 'all')
            ->addOption('full', 'f', InputOption::VALUE_NONE, 'Effectue un full import du catalogue disponible');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $parserName = $input->getArgument('parser');

        foreach ($this->parsers as $parser) {
            if ('all' !== $parserName && $parser->getCommandName() !== $parserName) {
                continue;
            }

            Monitor::writeln(sprintf(
                'Starting <info>%s</info>',
                $parser->getName()
            ));

            $parser->parse(!$input->getOption('full'));
            $nbEvents = $parser->getParsedEvents();

            Monitor::writeln(sprintf(
                '<info>%d</info> parsed events',
                $nbEvents
            ));
        }

        return Command::SUCCESS;
    }
}