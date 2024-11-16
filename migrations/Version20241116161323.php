<?php

declare(strict_types=1);

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

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241116161323 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $stmt = $this->connection->executeQuery('SELECT id, roles FROM user WHERE roles IS NOT NULL AND roles != \'a:0:{}\'');
        while ($row = $stmt->fetchAssociative()) {
            $roles = unserialize($row['roles']);
            $this->connection->executeStatement('UPDATE user SET roles = ? WHERE id = ?', [json_encode($roles), $row['id']]);
        }
        $this->addSql('UPDATE user SET roles = \'[]\' WHERE roles = \'a:0:{}\'');
        $this->addSql('ALTER TABLE user CHANGE roles roles JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `user` CHANGE roles roles LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\'');
    }
}
