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

/**
 * The second factors of a member, all opt-in: the secret of their authenticator app, their recovery codes and their
 * passkeys (App\Entity\WebauthnKey, its credential columns mapped by web-auth/webauthn-symfony-bundle).
 */
final class Version20261023214221 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The two-factor authentication of the members: authenticator app, recovery codes and passkeys';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD google_authenticator_secret VARCHAR(64) DEFAULT NULL, ADD backup_codes JSON DEFAULT NULL');
        $this->addSql('CREATE TABLE webauthn_key (public_key_credential_id LONGTEXT NOT NULL, type VARCHAR(255) NOT NULL, transports JSON NOT NULL, attestation_type VARCHAR(255) NOT NULL, trust_path JSON NOT NULL, aaguid TINYTEXT NOT NULL, credential_public_key LONGTEXT NOT NULL, user_handle VARCHAR(255) NOT NULL, counter INT NOT NULL, other_ui JSON DEFAULT NULL, backup_eligible TINYINT DEFAULT NULL, backup_status TINYINT DEFAULT NULL, uv_initialized TINYINT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, name VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, last_used_at DATETIME DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_4C03975CD17F50A6 (uuid), UNIQUE INDEX webauthn_key_user_name (user_id, name), INDEX IDX_4C03975CA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE webauthn_key ADD CONSTRAINT FK_4C03975CA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE webauthn_key');
        $this->addSql('ALTER TABLE `user` DROP google_authenticator_secret, DROP backup_codes');
    }
}
