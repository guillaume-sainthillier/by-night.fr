<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Importer\CityOfficialDataImporter;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Gives the cities the official code of their commune and the names GeoNames lacks (communes nouvelles,
 * arrondissements, communes déléguées), changing nothing else of them. Run again after a change of the official
 * geography (the INSEE's COG is published each January): the aliases are replaced.
 */
#[AsCommand('app:cities:import-official-data', 'Give the cities their official commune code and the commune names GeoNames lacks')]
final class CitiesImportOfficialDataCommand extends Command
{
    public function __construct(
        private readonly CityOfficialDataImporter $cityOfficialDataImporter,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('countries', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'The countries (FR, BE…), every country with cities by default');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var list<string> $countries */
        $countries = array_map(strtoupper(...), $input->getArgument('countries'));
        if ([] === $countries) {
            $countries = array_map(strval(...), $this->connection->fetchFirstColumn("SELECT DISTINCT country_id FROM admin_zone WHERE type = 'PPL' ORDER BY country_id"));
        }

        $result = $this->cityOfficialDataImporter->import($countries);

        $io->table(['Country', 'Cities given their code'], array_map(null, array_keys($result['codes']), array_values($result['codes'])));
        $io->success(\sprintf('%d aliases written.', $result['aliases']));

        return Command::SUCCESS;
    }
}
