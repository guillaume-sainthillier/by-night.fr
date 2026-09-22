<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Parser\Common;

use App\Dto\EventDto;
use App\Factory\AdminZone1Factory;
use App\Factory\CountryFactory;
use App\Parser\Common\OpenAgendaParser;
use App\Tests\AppKernelTestCase;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The feed's location.countryCode is not always the clean ISO alpha-2 it should be.
 * Whatever the parser puts in CountryDto::$code is compared against Country::$id all the
 * way down, so it must leave here canonical or not at all.
 */
#[ResetDatabase]
final class OpenAgendaParserTest extends AppKernelTestCase
{
    private OpenAgendaParser $parser;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = self::getContainer()->get(OpenAgendaParser::class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideFeedCountryCodes(): iterable
    {
        yield 'canonical' => ['FR'];
        yield 'lower-cased' => ['fr'];
        yield 'mixed case' => ['Fr'];
        yield 'padded' => [' FR '];
    }

    #[DataProvider('provideFeedCountryCodes')]
    public function testCountryCodeIsCanonicalisedToIsoAlpha2(string $feedValue): void
    {
        $dto = $this->arrayToDto(self::feedEvent(['countryCode' => $feedValue]));

        self::assertInstanceOf(EventDto::class, $dto);
        self::assertSame('FR', $dto->place?->country?->code);
        self::assertSame($dto->place?->country, $dto->place?->city?->country, 'City and place share one country DTO');
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideUnusableCountryCodes(): iterable
    {
        yield 'empty string' => [''];
        yield 'blank' => ['   '];
        yield 'null' => [null];
        yield 'alpha-3' => ['FRA'];
        yield 'country name' => ['France'];
    }

    #[DataProvider('provideUnusableCountryCodes')]
    public function testUnusableCountryCodeWithoutRegionSkipsTheEvent(mixed $feedValue): void
    {
        // Nothing to resolve the country from: better no event than an event that can
        // never be searched by location.
        self::assertNull($this->arrayToDto(self::feedEvent(['countryCode' => $feedValue, 'adminLevel1' => null, 'adminLevel2' => null])));
    }

    #[DataProvider('provideUnusableCountryCodes')]
    public function testUnusableCountryCodeFallsBackOnTheRegion(mixed $feedValue): void
    {
        $france = CountryFactory::france()->create();
        AdminZone1Factory::createOne(['name' => 'Occitanie', 'country' => $france]);

        $dto = $this->arrayToDto(self::feedEvent(['countryCode' => $feedValue, 'adminLevel1' => 'Occitanie', 'adminLevel2' => 'Haute-Garonne']));

        self::assertInstanceOf(EventDto::class, $dto);
        self::assertSame('FR', $dto->place?->country?->code, 'The region names the country when the code cannot');
    }

    private function arrayToDto(array $data): ?EventDto
    {
        $method = new ReflectionMethod(OpenAgendaParser::class, 'arrayToDto');

        /** @var EventDto|null $dto */
        $dto = $method->invoke($this->parser, $data, 'agenda-de-test');

        return $dto;
    }

    /**
     * A minimal event as the events endpoint returns it (detailed=1, monolingual=fr).
     *
     * @param array<string, mixed> $location overrides for the embedded location
     *
     * @return array<string, mixed>
     */
    private static function feedEvent(array $location = []): array
    {
        return [
            'uid' => 123456,
            'slug' => 'concert-au-parc',
            'title' => 'Concert au parc',
            'description' => 'Un concert en plein air dans le parc municipal.',
            'longDescription' => null,
            'updatedAt' => '2026-09-01T10:00:00+02:00',
            'timings' => [
                ['begin' => '2026-10-01T20:00:00+02:00', 'end' => '2026-10-01T22:00:00+02:00'],
            ],
            'keywords' => ['musique'],
            'image' => null,
            'registration' => [],
            'conditions' => 'Gratuit',
            'location' => array_replace([
                'uid' => 987,
                'name' => 'Parc municipal',
                'address' => '1 rue du Parc, 31000 Toulouse',
                'city' => 'Toulouse',
                'postalCode' => '31000',
                'countryCode' => 'FR',
                'adminLevel1' => 'Occitanie',
                'adminLevel2' => 'Haute-Garonne',
                'latitude' => 43.604652,
                'longitude' => 1.444209,
                'website' => null,
                'phone' => null,
                'email' => null,
            ], $location),
        ];
    }
}
