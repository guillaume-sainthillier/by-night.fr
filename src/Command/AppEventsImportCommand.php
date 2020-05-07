<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Parser\ParserInterface;
use App\Utils\Monitor;
use LogicException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AppEventsImportCommand extends Command
{
    /** @var ParserInterface[] */
    private array $parsers;

    public function __construct(array $parsers)
    {
        parent::__construct();

        $this->parsers = $parsers;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('app:events:import')
            ->setDescription('Ajouter / mettre à jour des nouveaux événements')
            ->addArgument('parser', InputArgument::REQUIRED, 'Nom du parser à lancer')
            ->addOption('full', 'f', InputOption::VALUE_NONE, 'Effectue un full import du catalogue disponible');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $parserName = $input->getArgument('parser');
        if (empty($this->parsers[$parserName])) {
            throw new LogicException(\sprintf('Le parser "%s" est introuvable', $parserName));
        }

        $parser = $this->parsers[$parserName];

        Monitor::writeln(\sprintf(
            'Lancement de <info>%s</info>',
            $parser::getParserName()
        ));

        $parser->parse(!$input->getOption('full'));
        $nbEvents = $parser->getParsedEvents();

        Monitor::writeln(\sprintf(
            '<info>%d</info> événements parsés',
            $nbEvents
        ));

        return 0;
    }
}
