<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 20/05/2017
 * Time: 14:04.
 */

namespace AppBundle\Importer;

use AppBundle\Entity\AdminZone;
use AppBundle\Entity\AdminZone1;
use AppBundle\Entity\AdminZone2;
use AppBundle\Entity\City;
use AppBundle\Entity\Country;
use AppBundle\Entity\ZipCity;
use Doctrine\ORM\EntityManagerInterface;

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
        $this->em      = $em;
        $this->dataDir = $dataDir;
    }

    public function import($id, $name = null, $capital = null, $locale = null)
    {
        /**
         * @var Country
         */
        $country = $this->em->getRepository('AppBundle:Country')->find($id);
        if (!$country) {
            $country = new Country();
            $country
                ->setId($id)
                ->setName($name)
                ->setCapital($capital)
                ->setLocale($locale);

            $this->em->persist($country);
            $this->em->flush();
        } else {
            $this->deleteRelatedDatas($country);
        }

        $this->createAdminZones($country);
        $this->createZipCities($country);
        $this->cleanDatas($country);
        $this->deleteEmptyDatas($country);
    }

    private function deleteEmptyDatas(Country $country)
    {
        $this->em->createQuery(' DELETE FROM AppBundle:ZipCity zc WHERE zc.parent IS NULL AND zc.country = :country')->execute([
            'country' => $country->getId(),
        ]);
    }

    private function cleanDatas(Country $country)
    {
        $this->em->getConnection()->executeUpdate('
            update admin_zone SET parent_id = NULL
            WHERE country_id = :country
        ', ['country' => $country->getId()]);

        $this->em->getConnection()->executeUpdate('
            update zip_city SET parent_id = NULL
            WHERE country_id = :country
        ', ['country' => $country->getId()]);

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
                SELECT a2.id FROM zip_city a2 GROUP BY a2.postal_code, a2.name HAVING(COUNT(a2.id)) > 1 AND a2.id = MAX(a2.id)
            ) as myId
        ) AND country_id = :country', ['country' => $country->getId()]);

        $this->em->getConnection()->executeUpdate('
        DELETE a2
        FROM admin_zone AS a2
        JOIN (
            SELECT t2.name, t2.admin1_code, t2.admin2_code, t2.country_id, t2.type, MAX(id) AS maxid
            FROM admin_zone AS t2
            JOIN (
                SELECT name, admin1_code, admin2_code, country_id, type, MAX(population) AS maxpop
                FROM admin_zone
                GROUP BY name, admin1_code, admin2_code, type, country_id
            ) AS t3
            ON t2.name = t3.name AND t2.admin1_code = t3.admin1_code AND t2.admin2_code = t3.admin2_code AND t2.country_id = t3.country_id AND t2.population = t3.maxpop AND t2.type = t3.type
            GROUP BY t2.name, t2.admin1_code, t2.admin2_code, t2.type, t2.country_id
        ) AS t4
        ON a2.name = t4.name AND a2.admin1_code = t4.admin1_code AND a2.admin2_code = t4.admin2_code AND a2.country_id = t4.country_id AND a2.type = t4.type AND a2.id != t4.maxid
        WHERE a2.type = \'PPL\' AND a2.country_id = :country', ['country' => $country->getId()]);

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

        $this->fixDatas($country);
    }

    private function fixDatas(Country $country)
    {
        switch ($country->getId()) {
            case 'BE':
                $this->em->getConnection()->executeUpdate('
                   update zip_city as zc
                    inner join admin_zone c ON (
                        c.type = \'PPL\'
                        AND SUBSTRING_INDEX(zc.name, \' \', 1) = c.name
                        AND zc.admin1_code = c.admin1_code
                        AND zc.admin2_code = c.admin2_code
                        AND zc.country_id = c.country_id
                    )
                    SET zc.parent_id = c.id
                    WHERE zc.parent_id IS NULL 
                    AND zc.country_id = :country
                ', ['country' => $country->getId()]);
                $this->manualAssociation([
                    'fontaine-leveque',
                    'la-roche',
                    'les-bons-villers',
                    'libramont',
                    'spiere',
                    'wortegem',
                    'amel'                 => 'ambleve-1',
                    'brunehaut'            => 'brunehault',
                    'brainele-chateau'     => 'braine-le-chateau',
                    'bruxelles'            => 'brussels',
                    'bullingen'            => 'bullange',
                    'comines-warneton'     => 'comines-1',
                    'erpe-'                => 'erpe',
                    'estinnes'             => 'estinnes-au-val',
                    'ecaussinnes'          => 'ecaussinnes-denghien',
                    'honnelles'            => 'onnezies',
                    'kelmis'               => 'la-calamine',
                    'kluisbergen'          => 'kruienberg',
                    'bruyere'              => 'bruyere-8',
                    'la-louviere'          => 'la-louviere-1',
                    'roeulx'               => 'roeulx-1',
                    'leuze'                => 'leuze-1',
                    'lierde'               => 'sint-maria-lierde',
                    'lo-reninge'           => 'reninge',
                    'maarkedal'            => 'maarke-kerkem',
                    'montignyl-e-tilleul'  => 'montigny-le-tilleul',
                    'morlanwelz'           => 'morlanwelz-mariemont',
                    'quevy-quevy-le-petit' => 'quevy-le-petit',
                    'quevy'                => 'quevy-le-grand',
                    'sankt-vith'           => 'saint-vith',
                    'vleteren'             => 'oostvleteren',
                    'oostende'             => 'mariakerke',
                    'mont-de-lenclus'      => 'orroir',
                ], $country);
                $this->em->getConnection()->executeUpdate("
                    UPDATE zip_city zc 
                    SET zc.parent_id = ( 
                      SELECT id 
                      FROM admin_zone c 
                      WHERE c.slug = 'brussels'
                    ) 
                    WHERE zc.parent_id IS NULL 
                    AND admin1_code = 'BRU'
                    AND admin2_code = 'BRU'
                    AND zc.country_id = 'BE'
                ");

                break;
            case 'MC':
                $this->manualAssociation([
                    'moneghetti',
                    'monte-carlo',
                    'fontvieille' => 'fontvieille-1',
                    'condamine'   => 'la-condamine',
                    '98000-'      => 'monaco',
                ], $country);

                break;
            case 'FR':
                $this->em->createQuery(" UPDATE AppBundle:AdminZone2 c SET c.slug = 'paris-temp' WHERE c.slug = 'paris'")->execute();
                $this->em->createQuery(" UPDATE AppBundle:City c SET c.slug = 'paris' WHERE c.slug = 'paris-1'")->execute();
                $this->em->createQuery(" UPDATE AppBundle:AdminZone2 c SET c.slug = 'paris-1' WHERE c.slug = 'paris-temp'")->execute();
                $this->insertCity(2995599, 'Marne-la-Vallée', 0, 48.83333, 2.63333, 11, 77, $country);
                $this->manualAssociation([
                    'la-defense',
                    'percy',
                    'moyon',
                    'montcuq',
                    'esplantas',
                    'les-abrets',
                    'montrevault',
                    'beaupreau',
                    'marne-la-vallee',
                    'epagny-2'                    => 'epagny',
                    'futuroscope'                 => 'chasseneuil-du-poitou',
                    'chemille-en-anjou'           => 'chemille-melay',
                    'courtaboeuf'                 => 'villebon-sur-yvette',
                    'ay-champagne'                => 'chalons-en-champagne',
                    'bagnoles-de-lorne-normandie' => 'bagnoles-de-lorne',
                    'boulazac'                    => 'boulazac-1',
                    'la-plagne'                   => 'la-plagne-2',
                    'carentan-les-marais'         => 'carentan',
                    'castelnau-dauzan-labarrere'  => 'castelnau-dauzan',
                    'charny-oree-de-puisaye'      => 'charny-2',
                    'chemery-chehery'             => 'chemery-sur-bar',
                    'conde-en-normandie'          => 'conde-sur-noireau',
                    'eurocentre'                  => 'fronton',
                    'gennes-val-de-loire'         => 'gennes',
                    'groslee-saint-benoit'        => 'groslee',
                    'juvigny-val-dandaine'        => 'juvigny-sous-andaine',
                    'la-chailleuse'               => 'chailleuse',
                    'la-haye'                     => 'la-haye-du-puits',
                    'le-bas-segala'               => 'la-bastide-leveque',
                ], $country);

                break;
        }
    }

    private function insertCity($id, $name, $population, $latitude, $longitude, $adminCode1, $adminCode2, Country $country)
    {
        $city                          = new City();
        list($adminCode1, $adminCode2) = $this->formatAdminZoneCodes($adminCode1, $adminCode2);
        $city
            ->setId($id)
            ->setName($name)
            ->setPopulation($population)
            ->setLatitude($latitude)
            ->setLongitude($longitude)
            ->setAdmin1Code($adminCode1)
            ->setAdmin2Code($adminCode2)
            ->setCountry($country);

        $this->em->merge($city);
        $this->em->flush();
    }

    private function manualAssociation(array $associations, Country $country)
    {
        foreach ($associations as $zipSlug => $citySlug) {
            if (is_numeric($zipSlug)) {
                $zipSlug = $citySlug;
            }

            $this->em->getConnection()->executeUpdate('
                UPDATE zip_city zc 
                SET zc.parent_id = ( 
                  SELECT id 
                  FROM admin_zone c 
                  WHERE c.slug = :city_slug 
                ) 
                WHERE zc.parent_id IS NULL 
                AND zc.slug LIKE :zip_slug
                AND zc.country_id = :country
            ', [
                'country'   => $country->getId(),
                'city_slug' => $citySlug,
                'zip_slug'  => '%'.$zipSlug.'%',
            ]);
        }
    }

    private function createZipCities(Country $country)
    {
        $fd = fopen($this->dataDir . '/'. $country->getId().'/zip.csv', 'r');
        if ($fd === false) {
            return;
        }

        $i = 0;
        while (($data = fgetcsv($fd, 1000, "\t")) !== false) {
            if (!$data[4] || !$data[6]) {
                continue;
            }
            ++$i;

            $city = new ZipCity();

            $data[1] = explode(' ', $data[1])[0];
            $data[2] = preg_replace("/ (\d+)$/", '', $data[2]);

            list($adminCode1, $adminCode2) = $this->formatAdminZoneCodes($data[4], $data[6]);

            $city
                ->setPostalCode($data[1])
                ->setName($data[2])
                ->setAdmin1Code($adminCode1)
                ->setAdmin2Code($adminCode2)
                ->setLatitude((float) $data[9])
                ->setLongitude((float) $data[10])
                ->setCountry($country);

            $this->em->persist($city);
            if ($i === 500) {
                $this->em->flush();
                $this->em->clear(ZipCity::class);
                $i = 0;
            }
        }
        $this->em->flush();
        fclose($fd);
    }

    private function createAdminZones(Country $country)
    {
        $fd = fopen($this->dataDir . '/'. $country->getId().'/cities.csv', 'r');
        if ($fd === false) {
            return;
        }

        $i = 0;
        while (($data = fgetcsv($fd, 3000, "\t")) !== false) {
            if (!(
                in_array($data[7], ['ADM1', 'ADM2']) || $data[6] === 'P'
//                ($data[6] === "P" && $data[14] > 0)
            )) {
                continue;
            }
            ++$i;

            if ($data[6] === 'P') {
                $entity = new City();
            } elseif ($data[7] === 'ADM1') {
                $entity = new AdminZone1();
            } else {
                $entity = new AdminZone2();
            }

            list($adminCode1, $adminCode2) = $this->formatAdminZoneCodes($data[10], $data[11]);

            $entity
                ->setId((int) $data[0])
                ->setName($data[1])
                ->setPopulation((int) $data[14])
                ->setLatitude((float) $data[4])
                ->setLongitude((float) $data[5])
                ->setAdmin1Code($adminCode1)
                ->setAdmin2Code($adminCode2)
                ->setCountry($country);

            $this->sanitizeAdminZone($entity);

            $this->em->persist($entity);
            if ($i === 50) {
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

    private function sanitizeAdminZone(AdminZone $entity)
    {
        switch ($entity->getCountry()->getId()) {
            case 'FR':
                if ($entity instanceof AdminZone2) {
                    $entity->setName(str_replace([
                            "Département d'",
                            "Département de l'",
                            'Département de la ',
                            'Département des ',
                            'Département de ',
                            'Département du ',
                            'Territoire de ',
                        ], '', $entity->getName())
                    );
                }

                break;
        }
    }

    private function formatAdminZoneCodes($code1, $code2)
    {
        return [
            $this->formatAdminZoneCode($code1),
            $this->formatAdminZoneCode($code2),
        ];
    }

    private function formatAdminZoneCode($code)
    {
        if ($code === '0' || $code === '00') {
            return $code;
        }

        $code = ltrim($code, '0');

        if ($code === '') {
            return null;
        }

        return $code;
    }

    private function deleteRelatedDatas(Country $country)
    {
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
