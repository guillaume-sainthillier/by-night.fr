<?php

namespace App\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170511192759 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql("update admin_zone as t1
            inner join admin_zone t2 ON (
                t2.type = 'ADM2'
                AND t2.admin1_code = t1.admin1_code
                AND t2.admin2_code = t2.admin2_code
                AND t2.country_id = t2.country_id
            )
            SET t1.parent_id = t2.id
            WHERE t1.type = 'PPL';");

        $this->addSql("update admin_zone as t1
            inner join admin_zone t2 ON (
                t2.type = 'ADM1'
                AND t2.admin1_code = t1.admin1_code
                AND t2.admin2_code = t2.admin2_code
                AND t2.country_id = t2.country_id
            )
            SET t1.parent_id = t2.id
            WHERE t1.type = 'ADM2';");
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('UPDATE admin_zone SET parent_id = NULL');
    }
}
