<?php

namespace Application\Migrations;

use AppBundle\Entity\AdminZone;
use AppBundle\Entity\AdminZone1;
use AppBundle\Entity\AdminZone2;
use AppBundle\Entity\City;
use AppBundle\Entity\Country;
use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;


/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170511184250 extends AbstractMigration implements ContainerAwareInterface
{
    /**
     * @var EntityManagerInterface $em
     */
    private $em;

    use ContainerAwareTrait;

    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $this->em = $this->container->get('doctrine')->getManager();

        $country = $this->createCountry();
        $this->createAdminZones($country);
    }

    private function createAdminZones(Country $country) {
        $fd = fopen(__DIR__ . '/datas/FR.csv', 'r');
        if($fd === false) {
            return;
        }

        $i = 0;
        while (($data = fgetcsv($fd, 3000, ";")) !== false) {
            if(! (
                in_array($data[7], ['ADM1', 'ADM2']) ||
                ($data[6] === "P" && $data[14] > 5000)
            )) {
                continue;
            }
            $i++;

            if($data[6] === "P") {
                $entity = new City();
            }elseif($data[7] === 'ADM1') {
                $entity = new AdminZone1();
            }else {
                $entity = new AdminZone2();
            }

            $entity
                ->setId((int)$data[0])
                ->setName($data[1])
                ->setPopulation((int)$data[14])
                ->setLatitude((float)$data[4])
                ->setLongitude((float)$data[5])
                ->setAdmin1Code($data[10])
                ->setAdmin2Code($data[11] ?: null)
                ->setCountry($country);

            $this->em->persist($entity);
            if($i === 50) {
                $this->em->flush();
                $this->em->clear(City::class);
                $this->em->clear(AdminZone::class);
                $this->em->clear(AdminZone1::class);
                $this->em->clear(AdminZone2::class);
                $i = 0;
            }
        }
        $this->em->flush();
        fclose($fd);
    }

    /**
     * @return Country
     */
    private function createCountry() {
        $country = new Country();
        $country
            ->setId("FR")
            ->setCapital("Paris")
            ->setName("France")
            ->setLocale("fr_FR");

        $this->em->persist($country);
        $this->em->flush();

        return $country;
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('UPDATE admin_zone SET parent_id = NULL;');
        $this->addSql('DELETE FROM admin_zone');
        $this->addSql('DELETE FROM country');
    }
}
