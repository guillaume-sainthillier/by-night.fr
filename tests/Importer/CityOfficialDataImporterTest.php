<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Importer;

use App\Comparator\CityComparator;
use App\Entity\City;
use App\Entity\Country;
use App\Factory\AdminZone2Factory;
use App\Factory\CityAliasFactory;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Importer\CityOfficialDataImporter;
use App\Repository\CityAliasRepository;
use App\Tests\AppKernelTestCase;
use Doctrine\DBAL\Connection;
use Override;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

use function Zenstruck\Foundry\Persistence\refresh;

use ZipArchive;

final class CityOfficialDataImporterTest extends AppKernelTestCase
{
    private Country $france;

    private Country $switzerland;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->france = CountryFactory::createOne(['id' => 'FR', 'name' => 'France']);
        $this->switzerland = CountryFactory::createOne(['id' => 'CH', 'name' => 'Suisse']);
    }

    public function testTheCitiesGetTheirCodeAndNothingElse(): void
    {
        $paris = $this->city(2988507, 'Paris', $this->france, 2_138_551);
        $rapperswil = $this->city(2659099, 'Rapperswil', $this->switzerland, 7_000);
        $department = AdminZone2Factory::createOne(['id' => 3013500, 'name' => 'Paris', 'country' => $this->france]);

        $result = $this->import(['FR', 'CH']);

        self::assertSame(['FR' => 1, 'CH' => 1], $result['codes']);
        refresh($paris);
        refresh($rapperswil);
        self::assertSame('75056', $paris->getOfficialCode(), 'The INSEE code, admin4');
        self::assertSame('3340', $rapperswil->getOfficialCode(), 'The OFS number, admin3');
        self::assertSame('Paris', $paris->getName(), 'GeoNames\' name is not taken');
        self::assertSame('Paris', refresh($department)->getName());
    }

    public function testTheCommunesNoCityIsNamedLeadToTheMostPopulatedCityOfTheirCode(): void
    {
        $pire = $this->city(2987046, 'Piré-sur-Seiche', $this->france, 2_065);
        $this->city(2998000, 'Lezé', $this->france, 0);
        $jona = $this->city(2660221, 'Jona', $this->switzerland, 17_655);
        $rapperswil = $this->city(2659099, 'Rapperswil', $this->switzerland, 34_776);

        $this->import(['FR', 'CH']);

        self::assertSame([$pire->getId()], $this->aliasCities('Piré-Chancé'), 'A commune nouvelle, from the INSEE\'s list');
        self::assertSame([$rapperswil->getId()], $this->aliasCities('Rapperswil-Jona'), 'A Swiss commune, from GeoNames\' ADM3');
        self::assertNotContains($jona->getId(), $this->aliasCities('Rapperswil-Jona'));
    }

    public function testACommuneDelegueeLeadsToTheCityBearingItsName(): void
    {
        $montaigu = $this->city(2992000, 'Montaigu', $this->france, 5_070);
        $village = $this->city(2980000, 'Saint-Georges-de-Montaigu', $this->france, 3_598);

        $this->import(['FR']);

        self::assertSame([$village->getId()], $this->aliasCities('Saint-Georges-de-Montaigu'), 'Not the commune\'s biggest town');
        self::assertSame([$montaigu->getId()], $this->aliasCities('Montaigu-Vendée'));
    }

    public function testAVillageFiledUnderAFormerCommuneGetsItsCommuneNouvelle(): void
    {
        $lire = $this->city(2998165, 'Liré', $this->france, 2_365);
        $champtoceaux = $this->city(3027008, 'Champtoceaux', $this->france, 1_840);

        $this->import(['FR']);

        self::assertSame('49126', refresh($lire)->getOfficialCode(), 'Orée d\'Anjou\'s, not Champtoceaux\'s 49069');
        self::assertSame([$lire->getId()], $this->aliasCities('Orée d\'Anjou'));
        self::assertSame([$champtoceaux->getId()], $this->aliasCities('Champtoceaux'));
    }

    public function testAnArrondissementLeadsToItsCityUnderEverySpelling(): void
    {
        $paris = $this->city(2988507, 'Paris', $this->france, 2_138_551);

        $this->import(['FR']);

        foreach (['Paris 9e Arrondissement', 'Paris 9e', 'Paris 9eme', 'Paris 9ème', 'Paris 09', 'Paris 9ème Arrondissement'] as $name) {
            self::assertSame([$paris->getId()], $this->aliasCities($name), $name);
        }

        self::assertSame([$paris->getId()], $this->aliasCities('Paris 1er'));
    }

    public function testACommuneLeadsToTheTownItsNameStartsWith(): void
    {
        $segre = $this->city(2975000, 'Segré', $this->france, 6_000);
        $this->city(2975001, 'Sainte-Gemmes-d’Andigné', $this->france, 7_000);

        $this->import(['FR']);

        self::assertSame([$segre->getId()], $this->aliasCities('Segré-en-Anjou Bleu'), 'Not the commune\'s most populated village');
    }

    public function testACommuneOfUnpeopledHamletsLeadsNowhere(): void
    {
        $this->city(2975002, 'Les Hameaux', $this->france, 0);

        $this->import(['FR']);

        self::assertSame([], $this->aliasCities('Val-des-Hameaux'), 'Any of its hamlets would be a guess');
    }

    public function testTheAliasesAreReplaced(): void
    {
        $paris = $this->city(2988507, 'Paris', $this->france, 2_138_551);
        CityAliasFactory::createOne(['city' => $paris, 'name' => 'Lutèce']);

        $first = $this->import(['FR']);
        $second = $this->import(['FR']);

        self::assertSame($first['aliases'], $second['aliases']);
        self::assertSame($second['aliases'], CityAliasFactory::count());
        self::assertSame([], $this->aliasCities('Lutèce'), 'An alias the lists no longer give goes');
    }

    public function testACountryLeftOutKeepsItsAliases(): void
    {
        $jona = $this->city(2660221, 'Jona', $this->switzerland, 17_655);
        CityAliasFactory::createOne(['city' => $jona, 'name' => 'Rapperswil-Jona']);

        $this->import(['FR']);

        self::assertSame([$jona->getId()], $this->aliasCities('Rapperswil-Jona'));
    }

    private function city(int $id, string $name, Country $country, int $population): City
    {
        return CityFactory::createOne(['id' => $id, 'name' => $name, 'country' => $country, 'population' => $population]);
    }

    /**
     * The cities a name leads to, as CityComparator looks it up.
     *
     * @return list<int>
     */
    private function aliasCities(string $name): array
    {
        return array_map(static fn (array $row): int => (int) $row['city_id'], self::getContainer()->get(Connection::class)->fetchAllAssociative('SELECT city_id FROM city_alias WHERE name_key = ? ORDER BY city_id', [CityComparator::nameKey($name)]));
    }

    /**
     * @param list<string> $countries
     *
     * @return array{codes: array<string, int>, aliases: int}
     */
    private function import(array $countries): array
    {
        $client = new MockHttpClient(static fn (string $method, string $url): MockResponse => match (true) {
            str_contains($url, '/dump/FR.zip') => new MockResponse(self::dump('FR', [
                // id, name, ascii name, alternate names, lat, lng, feature class, code, country, cc2, admin1-4, population
                [2988507, 'Paris', 'P', 'PPLC', '11', '75', '751', '75056', 2_138_551],
                [3013500, 'Paris', 'A', 'ADM2', '11', '75', '', '75056', 0],
                [2987046, 'Piré-sur-Seiche', 'P', 'PPL', '53', '35', '352', '35220', 2_065],
                [2998000, 'Lezé', 'P', 'PPL', '53', '35', '352', '35220', 0],
                [6432784, 'Piré-Chancé', 'A', 'ADM4', '53', '35', '352', '35220', 3_224],
                [2992000, 'Montaigu', 'P', 'PPL', '52', '85', '851', '85146', 5_070],
                [2980000, 'Saint-Georges-de-Montaigu', 'P', 'PPL', '52', '85', '851', '85146', 3_598],
                [2999999, 'Sans Code', 'P', 'PPL', '52', '85', '', '', 10],
                [2975000, 'Segré', 'P', 'PPL', '52', '49', '494', '49331', 6_000],
                [2975001, 'Sainte-Gemmes-d’Andigné', 'P', 'PPL', '52', '49', '494', '49331', 7_000],
                [2975002, 'Les Hameaux', 'P', 'PPL', '52', '49', '494', '49999', 0],
                [2998165, 'Liré', 'P', 'PPL', '52', '49', '491', '49069', 2_365],
                [3027008, 'Champtoceaux', 'P', 'PPL', '52', '49', '491', '49069', 1_840],
            ])),
            str_contains($url, '/dump/CH.zip') => new MockResponse(self::dump('CH', [
                [2659099, 'Rapperswil', 'P', 'PPL', 'SG', '1726', '3340', '', 34_776],
                [2660221, 'Jona', 'P', 'PPL', 'SG', '1726', '3340', '', 17_655],
                [7286859, 'Rapperswil-Jona', 'A', 'ADM3', 'SG', '1726', '3340', '', 27_277],
            ])),
            CityOfficialDataImporter::COMMUNES_URL === $url => new JsonMockResponse([
                ['nom' => 'Paris', 'code' => '75056', 'type' => 'commune-actuelle'],
                ['nom' => 'Paris 1er Arrondissement', 'code' => '75101', 'codeParent' => '75056', 'type' => 'arrondissement-municipal'],
                ['nom' => 'Paris 9e Arrondissement', 'code' => '75109', 'codeParent' => '75056', 'type' => 'arrondissement-municipal'],
                ['nom' => 'Piré-Chancé', 'code' => '35220', 'type' => 'commune-actuelle'],
                ['nom' => 'Montaigu-Vendée', 'code' => '85146', 'type' => 'commune-actuelle'],
                ['nom' => 'Pointe-à-Pitre', 'code' => '97120', 'type' => 'commune-actuelle'],
                ['nom' => 'Segré-en-Anjou Bleu', 'code' => '49331', 'type' => 'commune-actuelle'],
                ['nom' => 'Val-des-Hameaux', 'code' => '49999', 'type' => 'commune-actuelle'],
                ['nom' => 'Orée d\'Anjou', 'code' => '49126', 'type' => 'commune-actuelle'],
            ]),
            CityOfficialDataImporter::DELEGATED_COMMUNES_URL === $url => new JsonMockResponse([
                ['nom' => 'Saint-Georges-de-Montaigu', 'code' => '85214', 'chefLieu' => '85146', 'type' => 'commune-deleguee'],
                ['nom' => 'Champtoceaux', 'code' => '49069', 'chefLieu' => '49126', 'type' => 'commune-deleguee'],
                ['nom' => 'Drain', 'code' => '49126', 'chefLieu' => '49126', 'type' => 'commune-deleguee'],
            ]),
            default => self::fail(\sprintf('Unexpected request to %s', $url)),
        });

        $importer = new CityOfficialDataImporter($client, self::getContainer()->get(Connection::class), self::getContainer()->get(CityAliasRepository::class));

        return $importer->import($countries);
    }

    /**
     * A GeoNames dump as served: a zip holding <country>.txt.
     *
     * @param list<array{int, string, string, string, string, string, string, string, int}> $rows id, name, feature class and code, admin1 to admin4, population
     */
    private static function dump(string $country, array $rows): string
    {
        $lines = [];
        foreach ($rows as [$id, $name, $class, $code, $admin1, $admin2, $admin3, $admin4, $population]) {
            // A quote opening an alternate name must not swallow the next lines
            $lines[] = implode("\t", [$id, $name, $name, '"' . $name, '48.8', '2.3', $class, $code, $country, '', $admin1, $admin2, $admin3, $admin4, $population, '', '42', 'Europe/Paris', '2026-10-01']);
        }

        $filesystem = new Filesystem();
        $path = $filesystem->tempnam(sys_get_temp_dir(), 'geonames-test-', '.zip');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString($country . '.txt', implode("\n", $lines) . "\n");
        $zip->close();

        try {
            return (string) file_get_contents($path);
        } finally {
            $filesystem->remove($path);
        }
    }
}
