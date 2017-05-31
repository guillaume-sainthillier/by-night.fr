<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 20/05/2017
 * Time: 14:04
 */

namespace AppBundle\Importer;

use AppBundle\Entity\AdminZone;
use AppBundle\Entity\AdminZone1;
use AppBundle\Entity\AdminZone2;
use AppBundle\Entity\City;
use AppBundle\Entity\Country;
use AppBundle\Entity\ZipCity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;

class CountryImporter
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var string
     */
    private $dataDir;

    public function __construct(EntityManagerInterface $em, $dataDir)
    {
        $this->em = $em;
        $this->dataDir = $dataDir;
    }

    public function import($id, $name = null, $capital = null, $locale = null) {
//        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);

        /**
         * @var Country $country
         */
        $country = $this->em->getRepository('AppBundle:Country')->find($id);
        if(! $country) {
            $country = new Country();
            $country
                ->setId($id)
                ->setName($name)
                ->setCapital($capital)
                ->setLocale($locale);

            $this->em->persist($country);
            $this->em->flush();
        }else {
            $this->deleteRelatedDatas($country);
        }

        $this->createAdminZones($country);
        $this->createZipCities($country);
        $this->cleanDatas($country);
    }

    private function cleanDatas(Country $country) {
        $this->em->getConnection()->executeUpdate('
            update admin_zone as t1
            inner join admin_zone t2 ON (
                t2.type = \'ADM2\'
                AND t1.admin1_code = t2.admin1_code
                AND t1.admin2_code = t2.admin2_code
                AND t1.country_id = t2.country_id
            )
            SET t1.parent_id = t2.id
            WHERE t1.type = \'PPL\' AND t1.country_id = :country
        ', ['country' => $country->getId()]);

        $this->em->getConnection()->executeUpdate('
            update admin_zone as t1
            inner join admin_zone t2 ON (
                t2.type = \'ADM1\'
                AND t1.admin1_code = t2.admin1_code
                AND t1.country_id = t2.country_id
            )
            SET t1.parent_id = t2.id
            WHERE t1.type = \'ADM2\' AND t1.country_id = :country
        ', ['country' => $country->getId()]);

        $this->em->getConnection()->executeUpdate('
            update admin_zone as t1
            inner join admin_zone t2 ON (
                t2.type = \'ADM1\'
                AND t1.admin1_code = t2.admin1_code
                AND t1.country_id = t2.country_id
            )
            SET t1.parent_id = t2.id
            WHERE t1.type = \'PPL\' AND t1.parent_id IS NULL AND t1.country_id = :country
        ', ['country' => $country->getId()]);

        $this->em->getConnection()->executeUpdate('
            DELETE FROM zip_city WHERE id IN (
            SELECT * FROM (
                SELECT a2.id FROM zip_city a2 GROUP BY a2.postal_code, a2.name HAVING(COUNT(a2.id)) > 1 AND MAX(a2.id)
            ) as myId
        ) AND country_id = :country', ['country' => $country->getId()]);

        $this->em->getConnection()->executeUpdate('
            update zip_city as zc
            inner join admin_zone c ON (
                c.type = \'PPL\'
                AND zc.name = c.name
                AND zc.admin1_code = c.admin1_code
                AND zc.admin2_code = c.admin2_code
                AND zc.country_id = c.country_id
            )
            SET zc.parent_id = c.id
            WHERE zc.country_id = :country
        ', ['country' => $country->getId()]);

        if($country->getId() == 'FR') {
            $this->em->createQuery(" UPDATE AppBundle:AdminZone2 c SET c.slug = 'paris-temp' WHERE c.slug = 'paris'")->execute();
            $this->em->createQuery(" UPDATE AppBundle:City c SET c.slug = 'paris' WHERE c.slug = 'paris-1'")->execute();
            $this->em->createQuery(" UPDATE AppBundle:City c SET c.slug = 'paris-1' WHERE c.slug = 'paris-temp'")->execute();
        }
    }

    private function createZipCities(Country $country) {
        $fd = fopen($this->dataDir . '/'. $country->getId().'/zip.csv', 'r');
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

            list($adminCode1, $adminCode2) = $this->formatAdminZoneCodes($data[4], $data[6]);

            $city
                ->setPostalCode($data[1])
                ->setName($data[2])
                ->setAdmin1Code($adminCode1)
                ->setAdmin2Code($adminCode2)
                ->setLatitude((float)$data[9])
                ->setLongitude((float)$data[10])
                ->setCountry($country);

            $this->em->persist($city);
            if($i === 500) {
                $this->em->flush();
                $this->em->clear(ZipCity::class);
                $i = 0;
            }
        }
        $this->em->flush();
        fclose($fd);
    }

    private function createAdminZones(Country $country) {
        $fd = fopen($this->dataDir . '/'. $country->getId().'/cities.csv', 'r');
        if($fd === false) {
            return;
        }

        $i = 0;
        while (($data = fgetcsv($fd, 3000, "\t")) !== false) {
            if(! (
                in_array($data[7], ['ADM1', 'ADM2']) ||
                ($data[6] === "P" && $data[14] > 50)
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

            list($adminCode1, $adminCode2) = $this->formatAdminZoneCodes($data[10], $data[11]);

            $entity
                ->setId((int)$data[0])
                ->setName($data[1])
                ->setPopulation((int)$data[14])
                ->setLatitude((float)$data[4])
                ->setLongitude((float)$data[5])
                ->setAdmin1Code($adminCode1)
                ->setAdmin2Code($adminCode2)
                ->setCountry($country);

            if($entity instanceof AdminZone2) {
                $entity->setName(str_replace([
                        "Département d'",
                        "Département de l'",
                        "Département de la ",
                        "Département des ",
                        "Département de ",
                        "Département du ",
                        "Territoire de ",
                    ], '', $entity->getName())
                );
            }

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

    private function formatAdminZoneCodes($code1, $code2) {
        if($code1 === $code2) {
            $code2 = "";
        }

        return [
            $this->formatAdminZoneCode($code1),
            $this->formatAdminZoneCode($code2),
        ];
    }

    private function formatAdminZoneCode($code) {
        if($code === "0" || $code === "00") {
            return "1";
        }

        $code = ltrim($code, '0');
//
//        if($code === "") {
//            return null;
//        }

        return $code;
    }

    private function deleteRelatedDatas(Country $country) {
        $this->em->createQuery('
            UPDATE AppBundle:Place p
            SET p.zipCity = NULL
            WHERE p.zipCity IN (
                SELECT zc 
                FROM AppBundle:ZipCity zc 
                WHERE zc.country = :country
            )
        ')
            ->setParameter('country', $country->getId())
            ->execute()
        ;

        $this->em->createQuery('
            DELETE FROM AppBundle:ZipCity zc
            WHERE zc.country = :country
        ')
            ->setParameter('country', $country->getId())
            ->execute()
        ;

        $this->em->createQuery('
            UPDATE AppBundle:Place p
            SET p.city = NULL
            WHERE p.city IN (
                SELECT c 
                FROM AppBundle:City c 
                WHERE c.country = :country
            )
        ')
            ->setParameter('country', $country->getId())
            ->execute()
        ;

        $this->em->createQuery('
            DELETE FROM AppBundle:City c
            WHERE c.country = :country
        ')
            ->setParameter('country', $country->getId())
            ->execute()
        ;

        $this->em->createQuery('
            DELETE FROM AppBundle:AdminZone2 a
            WHERE a.country = :country
        ')
            ->setParameter('country', $country->getId())
            ->execute()
        ;

        $this->em->createQuery('
            DELETE FROM AppBundle:AdminZone1 a
            WHERE a.country = :country
        ')
            ->setParameter('country', $country->getId())
            ->execute()
        ;
    }
}
