<?php

namespace Application\Migrations;

use AppBundle\Entity\AdminZone;
use AppBundle\Entity\AdminZone1;
use AppBundle\Entity\AdminZone2;
use AppBundle\Entity\City;
use AppBundle\Entity\Country;
use AppBundle\Entity\ZipCity;
use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;


/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170511204250 extends AbstractMigration implements ContainerAwareInterface
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

        $country = $this->em->getRepository('AppBundle:Country')->find("FR");
        $this->createZipCities($country);
    }

    private function createZipCities(Country $country) {
        $fd = fopen(__DIR__ . '/datas/zip.txt', 'r');
        if($fd === false) {
            return;
        }

        $i = 0;
        while (($data = fgetcsv($fd, 1000, "\t")) !== false) {
            if(! $data[4] || ! $data[6]) {
                continue;
            }
            $i++;

            $city = new ZipCity();

            $data[1] = explode(" ", $data[1])[0];
            $data[2] = preg_replace("/ (\d+)$/", "", $data[2]);

            $city
                ->setPostalCode($data[1])
                ->setName($data[2])
                ->setAdmin1Code($data[4])
                ->setAdmin2Code($data[6])
                ->setLatitude((float)$data[9])
                ->setLongitude((float)$data[10])
                ->setCountry($country);

            $this->em->persist($city);
            if($i === 50) {
                $this->em->flush();
                $this->em->clear(ZipCity::class);
                $i = 0;
            }
        }
        $this->em->flush();
        fclose($fd);
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DELETE FROM zip_city');
    }
}
