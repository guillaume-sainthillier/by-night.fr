<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170211133111 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE User ADD slug VARCHAR(128) DEFAULT ""');
    }

    public function postUp(Schema $schema)
    {
        $em = $this->container->get('doctrine')->getManager();

        $users = $em->getRepository('TBNUserBundle:User')->findAll();
        foreach($users as $user){
            // need this so we force the generation of a new slug
            $user->setSlug(null);
            $em->persist($user);
        }
        $em->flush();

        $this->connection->executeQuery('ALTER TABLE User MODIFY slug VARCHAR(128) NOT NULL');
        $this->connection->executeQuery('CREATE UNIQUE INDEX UNIQ_2DA17977989D9B62 ON User (slug)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

//        $this->addSql('DROP INDEX UNIQ_2DA17977989D9B62 ON User');
        $this->addSql('ALTER TABLE User DROP slug');
    }
}
