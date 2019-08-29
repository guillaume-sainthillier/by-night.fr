<?php

namespace App\Command;

use App\Parser\ParserInterface;
use App\Utils\Monitor;
use LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AppEventsImportCommand extends AppCommand
{
    /** @var ParserInterface[] */
    private $parsers;

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
        parent::configure();

        $this
            ->setName('app:events:import')
            ->setDescription('Ajouter / mettre à jour des nouveaux événements sur By Night')
            ->addArgument('parser', InputArgument::REQUIRED, 'Nom du service à executer');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $parser = $input->getArgument('parser');
        if (empty($this->parsers[$parser])) {
            throw new LogicException(\sprintf(
                'Le parser "%s" est introuvable',
                $parser
            ));
        }

        $parser = $this->parsers[$parser];
        if (!$parser instanceof ParserInterface) {
            throw new LogicException(\sprintf(
                'Le service "%s" doit être une instance de ParserInterface',
                $parser
            ));
        }

        Monitor::writeln(\sprintf(
            'Lancement de <info>%s</info>',
            $parser->getNomData()
        ));

        $nbEvents = $parser->parse();

        Monitor::writeln(\sprintf(
            '<info>%d</info> événements parsés',
            $nbEvents
        ));
    }
}
