<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200509214558 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE event RENAME INDEX idx_2b41cd41a76ed395 TO IDX_3BAE0AA7A76ED395');
        $this->addSql('ALTER TABLE event RENAME INDEX idx_2b41cd41da6a219 TO IDX_3BAE0AA7DA6A219');
        $this->addSql('ALTER TABLE event RENAME INDEX idx_2b41cd41d7efa878 TO IDX_3BAE0AA7D7EFA878');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_757B22AA5D8BC1F8');
        $this->addSql('DROP INDEX UNIQ_2DA179775D8BC1F8 ON user');
        $this->addSql('ALTER TABLE user CHANGE info_id o_auth_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649F43BE553 FOREIGN KEY (o_auth_id) REFERENCES oauth (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649F43BE553 ON user (o_auth_id)');
        $this->addSql('ALTER TABLE user RENAME INDEX uniq_2da1797792fc23a8 TO UNIQ_8D93D64992FC23A8');
        $this->addSql('ALTER TABLE user RENAME INDEX uniq_2da17977a0d96fbf TO UNIQ_8D93D649A0D96FBF');
        $this->addSql('ALTER TABLE user RENAME INDEX uniq_2da17977c05fb297 TO UNIQ_8D93D649C05FB297');
        $this->addSql('ALTER TABLE user RENAME INDEX uniq_2da17977989d9b62 TO UNIQ_8D93D649989D9B62');
        $this->addSql('ALTER TABLE user RENAME INDEX idx_2da179778bac62af TO IDX_8D93D6498BAC62AF');
        $this->addSql('ALTER TABLE user_event RENAME INDEX idx_fd283f69a76ed395 TO IDX_D96CF1FFA76ED395');
        $this->addSql('ALTER TABLE user_event RENAME INDEX idx_fd283f6971f7e88b TO IDX_D96CF1FF71F7E88B');
        $this->addSql('ALTER TABLE comment RENAME INDEX idx_5bc96bf0a76ed395 TO IDX_9474526CA76ED395');
        $this->addSql('ALTER TABLE comment RENAME INDEX idx_5bc96bf071f7e88b TO IDX_9474526C71F7E88B');
        $this->addSql('ALTER TABLE comment RENAME INDEX idx_5bc96bf0727aca70 TO IDX_9474526C727ACA70');
        $this->addSql('ALTER TABLE place RENAME INDEX uniq_b5dc7cc9989d9b62 TO UNIQ_741D53CD989D9B62');
        $this->addSql('ALTER TABLE place RENAME INDEX idx_b5dc7cc98bac62af TO IDX_741D53CD8BAC62AF');
        $this->addSql('ALTER TABLE place RENAME INDEX idx_b5dc7cc9f92f3e70 TO IDX_741D53CDF92F3E70');
        $this->addSql('ALTER TABLE parser_data RENAME INDEX uniq_2a9385649f75d7b0 TO UNIQ_FE9A4A9C9F75D7B0');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE comment RENAME INDEX idx_9474526c727aca70 TO IDX_5BC96BF0727ACA70');
        $this->addSql('ALTER TABLE comment RENAME INDEX idx_9474526c71f7e88b TO IDX_5BC96BF071F7E88B');
        $this->addSql('ALTER TABLE comment RENAME INDEX idx_9474526ca76ed395 TO IDX_5BC96BF0A76ED395');
        $this->addSql('ALTER TABLE event RENAME INDEX idx_3bae0aa7a76ed395 TO IDX_2B41CD41A76ED395');
        $this->addSql('ALTER TABLE event RENAME INDEX idx_3bae0aa7d7efa878 TO IDX_2B41CD41D7EFA878');
        $this->addSql('ALTER TABLE event RENAME INDEX idx_3bae0aa7da6a219 TO IDX_2B41CD41DA6A219');
        $this->addSql('ALTER TABLE parser_data RENAME INDEX uniq_fe9a4a9c9f75d7b0 TO UNIQ_2A9385649F75D7B0');
        $this->addSql('ALTER TABLE place RENAME INDEX idx_741d53cd8bac62af TO IDX_B5DC7CC98BAC62AF');
        $this->addSql('ALTER TABLE place RENAME INDEX idx_741d53cdf92f3e70 TO IDX_B5DC7CC9F92F3E70');
        $this->addSql('ALTER TABLE place RENAME INDEX uniq_741d53cd989d9b62 TO UNIQ_B5DC7CC9989D9B62');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649F43BE553');
        $this->addSql('DROP INDEX UNIQ_8D93D649F43BE553 ON user');
        $this->addSql('ALTER TABLE user CHANGE o_auth_id info_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_757B22AA5D8BC1F8 FOREIGN KEY (info_id) REFERENCES oauth (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2DA179775D8BC1F8 ON user (info_id)');
        $this->addSql('ALTER TABLE user RENAME INDEX uniq_8d93d649a0d96fbf TO UNIQ_2DA17977A0D96FBF');
        $this->addSql('ALTER TABLE user RENAME INDEX idx_8d93d6498bac62af TO IDX_2DA179778BAC62AF');
        $this->addSql('ALTER TABLE user RENAME INDEX uniq_8d93d649989d9b62 TO UNIQ_2DA17977989D9B62');
        $this->addSql('ALTER TABLE user RENAME INDEX uniq_8d93d649c05fb297 TO UNIQ_2DA17977C05FB297');
        $this->addSql('ALTER TABLE user RENAME INDEX uniq_8d93d64992fc23a8 TO UNIQ_2DA1797792FC23A8');
        $this->addSql('ALTER TABLE user_event RENAME INDEX idx_d96cf1ff71f7e88b TO IDX_FD283F6971F7E88B');
        $this->addSql('ALTER TABLE user_event RENAME INDEX idx_d96cf1ffa76ed395 TO IDX_FD283F69A76ED395');
    }
}
