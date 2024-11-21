<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Importer;

use App\Entity\AdminZone;
use App\Entity\AdminZone1;
use App\Entity\AdminZone2;
use App\Entity\City;
use App\Entity\Country;
use App\Entity\ZipCity;
use App\Repository\CountryRepository;
use App\Utils\Monitor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use ZipArchive;

final readonly class CountryImporter
{
    /**
     * @var int
     */
    private const ZIP_CODES_PER_TRANSACTION = 500;

    /**
     * @var int
     */
    private const CITIES_PER_TRANSACTION = 50;

    public function __construct(private EntityManagerInterface $em, private string $dataDir, private CountryRepository $countryRepository)
    {
    }

    public function import(string $id, ?string $name = null, ?string $capital = null, ?string $locale = null): void
    {
        $country = $this->countryRepository->find($id);
        if (!$country) {
            $country = new Country();
            $country
                ->setId($id)
                ->setName($name)
                ->setCapital($capital)
                ->setLocale($locale);

            Monitor::writeln(\sprintf('Création du pays <info>%s (%s)</info>', $id, $country->getName()));
            $this->em->persist($country);
            $this->em->flush();
        } else {
            Monitor::writeln(\sprintf('Mise à jour du pays <info>%s (%s)</info>', $id, $country->getName()));
        }

        $this->createAdminZones($country);
        $this->createZipCities($country);
        $this->cleanDatas($country);
        $this->deleteEmptyDatas($country);
    }

    private function createAdminZones(Country $country): void
    {
        $filepath = $this->downloadAndExtractGeoname(
            'https://download.geonames.org/export/dump/' . $country->getId() . '.zip',
            'cities.csv',
            $country->getId()
        );

        $fd = fopen($filepath, 'r');
        if (false === $fd) {
            return;
        }

        $i = 0;
        while (false !== ($data = fgetcsv($fd, 3_000, "\t"))) {
            if (!\in_array($data[7], ['ADM1', 'ADM2']) && 'P' !== $data[6]) {
                continue;
            }

            ++$i;

            if ('P' === $data[6]) {
                $entity = new City();
            } elseif ('ADM1' === $data[7]) {
                $entity = new AdminZone1();
            } else {
                $entity = new AdminZone2();
            }

            $adminCode1 = $this->formatAdminZoneCode($data[10]);
            $adminCode2 = $this->formatAdminZoneCode($data[11]);

            $existingEntity = $this->em->getRepository($entity::class)->find((int) $data[0]);
            if (null !== $existingEntity) {
                $entity = $existingEntity;
            }

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
            if (self::CITIES_PER_TRANSACTION === $i) {
                $this->em->flush();
                $this->em->clear();
                $i = 0;
            }
        }

        $this->em->flush();
        fclose($fd);
    }

    private function downloadAndExtractGeoname(string $zipUrl, string $filename, ?string $countryId): string
    {
        // Create var/datas/<CountryCode>
        $filedir = $this->dataDir . \DIRECTORY_SEPARATOR . $countryId;
        $filepath = $filedir . \DIRECTORY_SEPARATOR . $filename;
        $fs = new Filesystem();
        $fs->mkdir($filedir);

        // Create /tmp/<CountryCode>
        $tempdir = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . $countryId;
        $tempfile = $tempdir . \DIRECTORY_SEPARATOR . $countryId . '.zip';
        $fs = new Filesystem();
        $fs->mkdir($tempdir);

        // Download content
        $content = file_get_contents($zipUrl);
        $fs->dumpFile($tempfile, $content);

        $zip = new ZipArchive();
        if (true !== $zip->open($tempfile)) {
            throw new IOException('Unable to unzip ' . $tempfile);
        }

        $zip->extractTo($tempdir);
        $zip->close();

        // move /tmp/<CountryCode>/<CountryCode>.txt to var/datas/<CountryCode>/<filename>
        $fs->rename(
            $tempdir . \DIRECTORY_SEPARATOR . $countryId . '.txt',
            $filepath,
            true
        );

        if (!$fs->exists($filepath)) {
            throw new IOException(\sprintf('File %s does not exists', $filepath));
        }

        return $filepath;
    }

    private function formatAdminZoneCode(?string $code): ?string
    {
        if (null === $code) {
            return null;
        }

        if ('0' === $code || '00' === $code) {
            return $code;
        }

        $code = ltrim($code, '0');

        if ('' === $code) {
            return null;
        }

        return $code;
    }

    private function sanitizeAdminZone(AdminZone $entity): void
    {
        if ('FR' == $entity->getCountry()->getId() && $entity instanceof AdminZone2) {
            $entity->setName(str_replace([
                "Département d'",
                "Département de l'",
                'Département de la ',
                'Département des ',
                'Département de ',
                'Département du ',
                'Territoire de ',
            ], '', (string) $entity->getName())
            );
        }
    }

    private function createZipCities(Country $country): void
    {
        $filepath = $this->downloadAndExtractGeoname(
            'https://download.geonames.org/export/zip/' . $country->getId() . '.zip',
            'zip.csv',
            $country->getId()
        );

        // Delete all zip cities
        $this->em->createQuery('
            DELETE FROM App:ZipCity zc
            WHERE zc.country = :country
        ')
            ->setParameter('country', $country->getId())
            ->execute();

        $fd = fopen($filepath, 'r');
        if (false === $fd) {
            return;
        }

        $i = 0;
        while (false !== ($data = fgetcsv($fd, 1_000, "\t"))) {
            if (!$data[4] || !$data[6]) {
                continue;
            }

            ++$i;

            $city = new ZipCity();

            $adminCode1 = $this->formatAdminZoneCode($data[4]);
            $adminCode2 = $this->formatAdminZoneCode($data[6]);

            $city
                ->setPostalCode($data[1])
                ->setName($data[2])
                ->setAdmin1Code($adminCode1)
                ->setAdmin1Name(null === $data[3] ? null : (trim((string) $data[3]) ?: null))
                ->setAdmin2Code($adminCode2)
                ->setAdmin2Name(null === $data[5] ? null : (trim((string) $data[5]) ?: null))
                ->setLatitude((float) $data[9])
                ->setLongitude((float) $data[10])
                ->setCountry($country);

            $this->em->persist($city);
            if (self::ZIP_CODES_PER_TRANSACTION === $i) {
                $this->em->flush();
                $this->em->clear();
                $i = 0;
            }
        }

        $this->em->flush();
        fclose($fd);
    }

    private function cleanDatas(Country $country): void
    {
        $this->em->getConnection()->executeStatement('
            UPDATE admin_zone SET parent_id = NULL
            WHERE country_id = :country
        ', ['country' => $country->getId()]);

        $this->em->getConnection()->executeStatement('
            UPDATE zip_city SET parent_id = NULL
            WHERE country_id = :country
        ', ['country' => $country->getId()]);

        $this->em->getConnection()->executeStatement('
            UPDATE admin_zone as t1
            INNER JOIN admin_zone t2 ON (
                t2.type = \'ADM2\'
                AND t1.admin1_code = t2.admin1_code
                AND t1.admin2_code = t2.admin2_code
                AND t1.country_id = t2.country_id
            )
            SET t1.parent_id = t2.id
            WHERE t1.type = \'PPL\' AND t1.country_id = :country
        ', ['country' => $country->getId()]);

        $this->em->getConnection()->executeStatement('
            UPDATE admin_zone as t1
            INNER JOIN admin_zone t2 ON (
                t2.type = \'ADM1\'
                AND t1.admin1_code = t2.admin1_code
                AND t1.country_id = t2.country_id
            )
            SET t1.parent_id = t2.id
            WHERE t1.type = \'ADM2\' AND t1.country_id = :country
        ', ['country' => $country->getId()]);

        $this->em->getConnection()->executeStatement('
            update admin_zone as t1
            inner join admin_zone t2 ON (
                t2.type = \'ADM1\'
                AND t1.admin1_code = t2.admin1_code
                AND t1.country_id = t2.country_id
            )
            SET t1.parent_id = t2.id
            WHERE t1.type = \'PPL\' AND t1.parent_id IS NULL AND t1.country_id = :country', [
            'country' => $country->getId(),
        ]);

        // Delete doublon
        $this->em->getConnection()->executeStatement('
            DELETE FROM zip_city WHERE id IN (
                SELECT * FROM (
                    SELECT a2.id FROM zip_city a2 GROUP BY a2.country_id, a2.postal_code, a2.name  HAVING(COUNT(a2.id)) > 1 AND a2.id <> MIN(a2.id)
                ) as myId
            ) AND country_id = :country', [
            'country' => $country->getId(),
        ]);

        // Delete doublon
        $this->em->getConnection()->executeStatement('
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
            WHERE a2.type = \'PPL\' AND a2.country_id = :country', [
            'country' => $country->getId(),
        ]);

        $this->em->getConnection()->executeStatement('
            UPDATE zip_city as zc
            INNER JOIN admin_zone c ON (
                c.type = \'PPL\'
                AND zc.name = c.name
                AND zc.admin1_code = c.admin1_code
                AND zc.admin2_code = c.admin2_code
                AND zc.country_id = c.country_id
            )
            SET zc.parent_id = c.id
            WHERE zc.country_id = :country', [
            'country' => $country->getId(),
        ]);

        $this->fixDatas($country);

        $this->em->getConnection()->executeStatement('
            UPDATE zip_city as zc
            INNER JOIN admin_zone c ON (
                c.type = \'PPL\'
                AND ROUND(zc.latitude, 3) = ROUND(c.latitude, 3)
                AND ROUND(zc.longitude, 3) = ROUND(c.longitude, 3)
                AND zc.country_id = c.country_id
            )
            SET zc.parent_id = c.id
            WHERE zc.parent_id IS NULL
            AND zc.country_id = :country', [
            'country' => $country->getId(),
        ]);
    }

    private function fixDatas(Country $country): void
    {
        switch ($country->getId()) {
            case 'BE':
                $this->em->getConnection()->executeStatement('
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
                    'amel' => 'ambleve-1',
                    'brunehaut' => 'brunehault',
                    'brainele-chateau' => 'braine-le-chateau',
                    'bruxelles' => 'brussels',
                    'bullingen' => 'bullange',
                    'comines-warneton' => 'comines-1',
                    'erpe-' => 'erpe',
                    'estinnes' => 'estinnes-au-val',
                    'ecaussinnes' => 'ecaussinnes-denghien',
                    'honnelles' => 'onnezies',
                    'kelmis' => 'la-calamine',
                    'kluisbergen' => 'kruienberg',
                    'bruyere' => 'bruyere-8',
                    'la-louviere' => 'la-louviere-1',
                    'roeulx' => 'roeulx-1',
                    'leuze' => 'leuze-1',
                    'lierde' => 'sint-maria-lierde',
                    'lo-reninge' => 'reninge',
                    'maarkedal' => 'maarke-kerkem',
                    'montignyl-e-tilleul' => 'montigny-le-tilleul',
                    'morlanwelz' => 'morlanwelz-mariemont',
                    'quevy-quevy-le-petit' => 'quevy-le-petit',
                    'quevy' => 'quevy-le-grand',
                    'sankt-vith' => 'saint-vith',
                    'vleteren' => 'oostvleteren',
                    'oostende' => 'mariakerke',
                    'mont-de-lenclus' => 'orroir',
                ], $country);
                $this->em->getConnection()->executeStatement("
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
                    'condamine' => 'la-condamine',
                    '98000-' => 'monaco-1',
                ], $country);

                break;
            case 'FR':
                $this->em->createQuery(" UPDATE App:AdminZone2 c SET c.slug = 'paris-temp' WHERE c.slug = 'paris'")->execute();
                $this->em->createQuery(" UPDATE App:City c SET c.slug = 'paris' WHERE c.slug = 'paris-1'")->execute();
                $this->em->createQuery(" UPDATE App:AdminZone2 c SET c.slug = 'paris-1' WHERE c.slug = 'paris-temp'")->execute();
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
                    'epagny-2' => 'epagny',
                    'futuroscope' => 'chasseneuil-du-poitou',
                    'chemille-en-anjou' => 'chemille-melay',
                    'courtaboeuf' => 'villebon-sur-yvette',
                    'ay-champagne' => 'chalons-en-champagne',
                    'bagnoles-de-lorne-normandie' => 'bagnoles-de-lorne',
                    'boulazac' => 'boulazac-1',
                    'la-plagne' => 'la-plagne-2',
                    'carentan-les-marais' => 'carentan',
                    'castelnau-dauzan-labarrere' => 'castelnau-dauzan',
                    'charny-oree-de-puisaye' => 'charny-2',
                    'chemery-chehery' => 'chemery-sur-bar',
                    'conde-en-normandie' => 'conde-sur-noireau',
                    'eurocentre' => 'fronton',
                    'gennes-val-de-loire' => 'gennes',
                    'groslee-saint-benoit' => 'groslee',
                    'juvigny-val-dandaine' => 'juvigny-sous-andaine',
                    'la-chailleuse' => 'chailleuse',
                    'la-haye' => 'la-haye-du-puits',
                    'le-bas-segala' => 'la-bastide-leveque',
                ], $country);

                break;
        }
    }

    private function manualAssociation(array $associations, Country $country): void
    {
        foreach ($associations as $zipSlug => $citySlug) {
            if (is_numeric($zipSlug)) {
                $zipSlug = $citySlug;
            }

            $this->em->getConnection()->executeStatement('
                UPDATE zip_city zc
                SET zc.parent_id = (
                  SELECT id
                  FROM admin_zone c
                  WHERE c.slug = :city_slug
                    AND c.country_id = zc.country_id
                )
                WHERE zc.parent_id IS NULL
                AND zc.slug LIKE :zip_slug
                AND zc.country_id = :country
            ', [
                'country' => $country->getId(),
                'city_slug' => $citySlug,
                'zip_slug' => '%' . $zipSlug . '%',
            ]);
        }
    }

    private function deleteEmptyDatas(Country $country): void
    {
        $this->em->createQuery(' DELETE FROM App:ZipCity zc WHERE zc.parent IS NULL AND zc.country = :country')->execute([
            'country' => $country->getId(),
        ]);
    }
}
