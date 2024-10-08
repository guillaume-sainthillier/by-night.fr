<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Importer\CountryImporter;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

#[AsCommand('app:country:import', 'Ajoute un nouveau pays')]
final class CountryImportCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    public function __construct(private readonly CountryImporter $countryImporter)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED)
            ->addArgument('name', InputArgument::OPTIONAL)
            ->addArgument('capital', InputArgument::OPTIONAL)
            ->addArgument('locale', InputArgument::OPTIONAL);
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $optionalParams = [];
        $atLeastOneInfo = false;
        foreach ($input->getArguments() as $name => $value) {
            if (!\in_array($name, ['id', 'command'], true)) {
                continue;
            }

            $optionalParams[] = $name;
            $atLeastOneInfo = $atLeastOneInfo || !empty($value);
        }

        if ($atLeastOneInfo) {
            foreach ($optionalParams as $name) {
                if (!$input->getArgument($name)) {
                    $value = $this->askParam($name, $input, $output);
                    $input->setArgument($name, $value);
                }
            }
        }
    }

    private function askParam(string $name, InputInterface $input, OutputInterface $output): string
    {
        $question = new Question(\sprintf("Valeur de l'argument %s : ", $name));
        $question->setValidator(static function ($value) {
            if (empty($value)) {
                throw new Exception('Cette valeur ne peut pas être vide');
            }

            return $value;
        });

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        return $helper->ask($input, $output, $question);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->countryImporter->import(
            $input->getArgument('id'),
            $input->getArgument('name'),
            $input->getArgument('capital'),
            $input->getArgument('locale')
        );

        return Command::SUCCESS;
    }
}
