<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Importer\CountryImporter;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class AppCountryImportCommand extends Command
{
    private CountryImporter $countryImporter;

    /**
     * {@inheritdoc}
     */
    public function __construct(CountryImporter $countryImporter)
    {
        parent::__construct();

        $this->countryImporter = $countryImporter;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('app:country:import')
            ->setDescription('Ajoute un nouveau pays')
            ->addArgument('id', InputArgument::REQUIRED)
            ->addArgument('name', InputArgument::OPTIONAL)
            ->addArgument('capital', InputArgument::OPTIONAL)
            ->addArgument('locale', InputArgument::OPTIONAL);
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
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
        $question = new Question(sprintf("Valeur de l'argument %s : ", $name));
        $question->setValidator(function ($value) {
            if (empty($value)) {
                throw new Exception('Cette valeur ne peut pas être vide');
            }

            return $value;
        });

        return $this->getHelper('question')->ask($input, $output, $question);
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

        return 0;
    }
}
