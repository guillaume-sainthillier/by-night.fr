<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Importer;

use App\Comparator\CityComparator;
use App\Repository\CityAliasRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use ZipArchive;

/**
 * Gives the cities the official code of their commune and the names the sources use that GeoNames lacks, without
 * touching anything else of them: CountryImporter would rename them (and change their URLs) to GeoNames' current
 * names, "Dunkirk", "Geneva", and add its quarters.
 *
 * - The code (City::$officialCode) is read from GeoNames' dump: INSEE (admin4) in France and overseas, INS (admin4)
 *   in Belgium, OFS (admin3) in Switzerland, the villages merged into a commune bearing the commune's.
 * - The aliases (CityAlias) are the names of the communes, given to a city of their code (aliases()): France's from the official geography (geo.api.gouv.fr, the INSEE's COG: communes, municipal arrondissements
 *   and their spellings, communes déléguées and associées), Belgium's and Switzerland's from GeoNames' communes (ADM4,
 *   ADM3), which follow Statbel's and the OFS's lists.
 */
final readonly class CityOfficialDataImporter
{
    public const string GEONAMES_DUMP_URL = 'https://download.geonames.org/export/dump/%s.zip';

    public const string COMMUNES_URL = 'https://geo.api.gouv.fr/communes?type=commune-actuelle,arrondissement-municipal&fields=nom,code,codeParent,type';

    public const string DELEGATED_COMMUNES_URL = 'https://geo.api.gouv.fr/communes_associees_deleguees?fields=nom,code,chefLieu,type';

    private const int CODES_PER_UPDATE = 1_000;

    /** The overseas départements and collectivities GeoNames files as countries, by the start of their INSEE codes */
    private const array OVERSEAS_BY_CODE = ['971' => 'GP', '972' => 'MQ', '973' => 'GF', '974' => 'RE', '975' => 'PM', '976' => 'YT', '977' => 'BL', '978' => 'MF'];

    /** The countries whose communes geo.api.gouv.fr lists */
    private const array FRENCH_COUNTRIES = ['FR', 'GP', 'MQ', 'GF', 'RE', 'PM', 'YT', 'BL', 'MF'];

    /** The feature code of a commune in GeoNames, for the countries whose communes are read there */
    private const array GEONAMES_COMMUNES = ['BE' => 'ADM4', 'CH' => 'ADM3'];

    public function __construct(
        private HttpClientInterface $httpClient,
        private Connection $connection,
        private CityAliasRepository $cityAliasRepository,
    ) {
    }

    /**
     * The official code of a GeoNames row's commune: its admin3 code in Switzerland, its admin4 code elsewhere.
     *
     * @param array<int, string|null> $row the columns of a line of a GeoNames dump
     */
    public static function officialCode(string $countryId, array $row): ?string
    {
        $code = trim($row['CH' === $countryId ? 12 : 13] ?? '');

        return '' === $code ? null : $code;
    }

    /**
     * @param list<string> $countryIds
     *
     * @return array{codes: array<string, int>, aliases: int} the cities given a code by country, the aliases written
     */
    public function import(array $countryIds): array
    {
        $codes = [];
        $communes = [];
        foreach ($countryIds as $countryId) {
            [$cityCodes, $communes[$countryId]] = $this->readDump($countryId);
            $codes[$countryId] = $this->saveCodes($countryId, $cityCodes);
        }

        if ([] !== array_intersect($countryIds, self::FRENCH_COUNTRIES)) {
            foreach ($this->readFrenchCommunes() as [$countryId, $code, $name]) {
                if (\in_array($countryId, $countryIds, true)) {
                    $communes[$countryId][] = [$code, $name];
                }
            }
        }

        return ['codes' => $codes, 'aliases' => $this->cityAliasRepository->replaceAll($countryIds, $this->aliases($communes))];
    }

    /**
     * @return array{array<int, string>, list<array{string, string}>} the code of each city by its id, the communes
     *                                                                GeoNames lists for the country (code and name)
     */
    private function readDump(string $countryId): array
    {
        $filesystem = new Filesystem();
        $zipPath = $filesystem->tempnam(sys_get_temp_dir(), 'geonames-', '.zip');

        try {
            $response = $this->httpClient->request('GET', \sprintf(self::GEONAMES_DUMP_URL, $countryId));
            $file = fopen($zipPath, 'w');
            if (false === $file) {
                throw new RuntimeException(\sprintf('Unable to write %s', $zipPath));
            }

            foreach ($this->httpClient->stream($response) as $chunk) {
                fwrite($file, $chunk->getContent());
            }

            fclose($file);

            $zip = new ZipArchive();
            if (true !== $zip->open($zipPath)) {
                throw new RuntimeException(\sprintf('Unable to open the GeoNames dump of %s', $countryId));
            }

            $lines = $zip->getStream($countryId . '.txt');
            if (false === $lines) {
                throw new RuntimeException(\sprintf('The GeoNames dump of %s has no %s.txt', $countryId, $countryId));
            }

            $codes = [];
            $communes = [];
            $communeFeature = self::GEONAMES_COMMUNES[$countryId] ?? null;
            // Split by hand: a quote opening an alternate name would make fgetcsv() read on into the next lines
            while (false !== ($line = fgets($lines))) {
                $row = explode("\t", rtrim($line, "\r\n"));
                $code = self::officialCode($countryId, $row);
                if (null === $code) {
                    continue;
                }

                if ('P' === ($row[6] ?? null)) {
                    $codes[(int) $row[0]] = $code;
                } elseif (null !== $communeFeature && $communeFeature === ($row[7] ?? null)) {
                    $communes[] = [$code, $row[1]];
                }
            }

            fclose($lines);
            $zip->close();

            return [$codes, $communes];
        } finally {
            $filesystem->remove($zipPath);
        }
    }

    /**
     * Writes the codes on the cities the database has: only that column, and only on the cities (CountryImporter
     * left out some of GeoNames' places, and keeps the other zones).
     *
     * @param array<int, string> $codes
     */
    private function saveCodes(string $countryId, array $codes): int
    {
        $saved = 0;
        foreach (array_chunk($codes, self::CODES_PER_UPDATE, true) as $chunk) {
            $cases = implode(' ', array_fill(0, \count($chunk), 'WHEN ? THEN ?'));
            $parameters = [];
            $types = [];
            foreach ($chunk as $id => $code) {
                array_push($parameters, $id, $code);
                array_push($types, ParameterType::INTEGER, ParameterType::STRING);
            }

            $saved += (int) $this->connection->executeStatement(
                \sprintf("UPDATE admin_zone SET official_code = CASE id %s END WHERE type = 'PPL' AND country_id = ? AND id IN (?)", $cases),
                [...$parameters, $countryId, array_keys($chunk)],
                [...$types, ParameterType::STRING, ArrayParameterType::INTEGER],
            );
        }

        return $saved;
    }

    /**
     * The French communes, their municipal arrondissements and their communes déléguées or associées, with the code
     * of the commune whose town they name.
     *
     * @return iterable<array{string, string, string}> the country, the code and the name
     */
    private function readFrenchCommunes(): iterable
    {
        foreach ($this->httpClient->request('GET', self::COMMUNES_URL)->toArray() as $commune) {
            $code = 'arrondissement-municipal' === $commune['type'] ? $commune['codeParent'] : $commune['code'];
            foreach ($this->spellings($commune['nom']) as $name) {
                yield [$this->frenchCountry($code), $code, $name];
            }
        }

        foreach ($this->httpClient->request('GET', self::DELEGATED_COMMUNES_URL)->toArray() as $commune) {
            yield [$this->frenchCountry($commune['chefLieu']), $commune['chefLieu'], $commune['nom']];
        }
    }

    private function frenchCountry(string $code): string
    {
        return self::OVERSEAS_BY_CODE[substr($code, 0, 3)] ?? 'FR';
    }

    /**
     * A commune's name, and for a municipal arrondissement the ways the sources write it: "Paris 9e Arrondissement",
     * "PARIS 9EME", "Paris 9ème", "Paris 09", "Lyon 1er".
     *
     * @return list<string>
     */
    private function spellings(string $name): array
    {
        if (1 !== preg_match('/^(.+) (\d{1,2})(?:er|e) Arrondissement$/u', $name, $matches)) {
            return [$name];
        }

        [, $town, $number] = $matches;
        $ordinals = '1' === $number ? ['1er', '1ere', '1ère', '1e'] : [$number . 'e', $number . 'eme', $number . 'ème'];
        $spellings = [$name, \sprintf('%s %02d', $town, $number), \sprintf('%s %s', $town, $number)];
        foreach ($ordinals as $ordinal) {
            $spellings[] = \sprintf('%s %s', $town, $ordinal);
            $spellings[] = \sprintf('%s %s Arrondissement', $town, $ordinal);
        }

        return $spellings;
    }

    /**
     * The aliases to write: each commune name, given to a city of its code (town()). A name a city bears is kept too:
     * the cities' names are compared hyphens and all (CityComparator::getMostMatching()), the aliases' key reads "Bourg
     * Saint Maurice" as "Bourg-Saint-Maurice".
     *
     * @param array<string, list<array{string, string}>> $communes the code and the name of the communes, by country
     *
     * @return iterable<array{int, string}> the city's id and the name
     */
    private function aliases(array $communes): iterable
    {
        foreach ($communes as $countryId => $countryCommunes) {
            $rows = $this->connection->fetchAllAssociative(
                "SELECT id, name, population, official_code FROM admin_zone WHERE type = 'PPL' AND country_id = ? AND official_code IS NOT NULL ORDER BY population DESC, id ASC",
                [$countryId],
            );

            // The cities of each code, the most populated first
            $cities = [];
            foreach ($rows as $row) {
                $cities[(string) $row['official_code']][] = ['id' => (int) $row['id'], 'key' => CityComparator::nameKey((string) $row['name']), 'population' => (int) $row['population']];
            }

            $written = [];
            foreach ($countryCommunes as [$code, $name]) {
                $key = CityComparator::nameKey($name);
                $town = '' === $key ? null : $this->town($cities[$code] ?? [], $key);
                if (null === $town || isset($written[$town][$key])) {
                    continue;
                }

                $written[$town][$key] = true;

                yield [$town, $name];
            }
        }
    }

    /**
     * The city of its code a commune name leads to: the one bearing it (a commune déléguée GeoNames knows as a
     * village, written "Saint Georges de Montaigu"), else the one its name starts with ("Segré" for "Segré-en-Anjou
     * Bleu"), else the most populated, when GeoNames knows its population: without it, the first city of the code
     * would be any of its hamlets (Leuze-en-Hainaut's are all at 0, the town missing from them), so no alias.
     *
     * @param list<array{id: int, key: string, population: int}> $cities the cities of the code, the most populated first
     */
    private function town(array $cities, string $key): ?int
    {
        foreach ($cities as $city) {
            if ($city['key'] === $key) {
                return $city['id'];
            }
        }

        foreach ($cities as $city) {
            if (str_starts_with($key, $city['key'] . '-')) {
                return $city['id'];
            }
        }

        $first = $cities[0] ?? null;

        return null !== $first && $first['population'] > 0 ? $first['id'] : null;
    }
}
