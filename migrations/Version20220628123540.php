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
final class Version20220628123540 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin_zone DROP CONSTRAINT FK_80F242E7F92F3E70');
        $this->addSql('ALTER TABLE event DROP CONSTRAINT FK_2B41CD41D7EFA878');
        $this->addSql('ALTER TABLE place DROP CONSTRAINT FK_B5DC7CC9F92F3E70');
        $this->addSql('ALTER TABLE zip_city DROP CONSTRAINT FK_FBE2D3F4F92F3E70');

        $this->addSql('ALTER TABLE admin_zone CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE comment CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE country CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE event CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE migration_versions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE oauth CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE parser_data CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE parser_history CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE place CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE place_metadata CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE reset_password_request CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE `user` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE user_event CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE zip_city CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        $this->addSql('ALTER TABLE admin_zone ADD CONSTRAINT FK_80F242E7F92F3E70 FOREIGN KEY (country_id) REFERENCES country (id)');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7D7EFA878 FOREIGN KEY (place_country_id) REFERENCES country (id)');
        $this->addSql('ALTER TABLE place ADD CONSTRAINT FK_741D53CDF92F3E70 FOREIGN KEY (country_id) REFERENCES country (id)');
        $this->addSql('ALTER TABLE zip_city ADD CONSTRAINT FK_FBE2D3F4F92F3E70 FOREIGN KEY (country_id) REFERENCES country (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
