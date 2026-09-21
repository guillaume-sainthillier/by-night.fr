<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace DoctrineMigrations;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921155549 extends AbstractMigration
{
    private const array FIVE_DIGITS = ['FR', 'MC', 'GP', 'MQ', 'GF', 'RE', 'YT'];

    private const array FOUR_DIGITS = ['CH', 'BE'];

    public function getDescription(): string
    {
        return 'Fill country.postal_code_regex so the import Firewall validates postal codes per country (5 digits for the French system, 4 for Switzerland and Belgium)';
    }

    public function up(Schema $schema): void
    {
        // Metropolitan France, Monaco and the overseas departments share the French 5-digit system
        $this->addSql(
            'UPDATE country SET postal_code_regex = :regex WHERE id IN (:ids)',
            ['regex' => '^[0-9]{5}$', 'ids' => self::FIVE_DIGITS],
            ['ids' => ArrayParameterType::STRING],
        );

        // Swiss and Belgian codes are 4 digits and never start with 0
        $this->addSql(
            'UPDATE country SET postal_code_regex = :regex WHERE id IN (:ids)',
            ['regex' => '^[1-9][0-9]{3}$', 'ids' => self::FOUR_DIGITS],
            ['ids' => ArrayParameterType::STRING],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'UPDATE country SET postal_code_regex = NULL WHERE id IN (:ids)',
            ['ids' => [...self::FIVE_DIGITS, ...self::FOUR_DIGITS]],
            ['ids' => ArrayParameterType::STRING],
        );
    }
}
