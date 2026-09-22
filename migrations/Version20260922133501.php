<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922133501 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'parser_history.from_data lists every parser of the batch (json) instead of a "?" placeholder';
    }

    public function up(Schema $schema): void
    {
        // The legacy parser names of the oldest rows (bikini, facebook, ...) become one-element
        // lists; a "?" placeholder or an empty string both meant "no known source".
        $this->addSql("UPDATE parser_history SET from_data = JSON_ARRAY(from_data) WHERE from_data NOT IN ('', '?')");
        $this->addSql("UPDATE parser_history SET from_data = '[]' WHERE from_data IN ('', '?')");
        $this->addSql('ALTER TABLE parser_history CHANGE from_data from_data JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Back to a single name per row: the first of the list, "?" when it is empty
        $this->addSql('ALTER TABLE parser_history CHANGE from_data from_data LONGTEXT NOT NULL');
        $this->addSql('UPDATE parser_history SET from_data = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(from_data, \'$[0]\')), \'?\')');
        $this->addSql('ALTER TABLE parser_history CHANGE from_data from_data VARCHAR(127) NOT NULL');
    }
}
