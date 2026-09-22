<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Import;

use App\Dto\CountryDto;
use App\Factory\CountryFactory;
use App\Import\PostalCodeChecker;
use App\Tests\AppKernelTestCase;
use Override;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class PostalCodeCheckerTest extends AppKernelTestCase
{
    private PostalCodeChecker $checker;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = self::getContainer()->get(PostalCodeChecker::class);

        CountryFactory::france()->create();
        CountryFactory::switzerland()->create();
        CountryFactory::belgium()->create();
    }

    public function testEmptyPostalCodeIsAlwaysAccepted(): void
    {
        self::assertTrue($this->checker->accepts(self::countryByCode('FR'), null));
        self::assertTrue($this->checker->accepts(self::countryByCode('CH'), ''));
        self::assertTrue($this->checker->accepts(null, 'n/a'), 'A code without any digit counts as missing, not as wrong.');
    }

    public function testFrenchCodesMustHaveFiveDigits(): void
    {
        self::assertTrue($this->checker->accepts(self::countryByCode('FR'), '31000'));
        self::assertTrue($this->checker->accepts(self::countryByCode('FR'), 'F-31 000'), 'Only the digits are compared.');
        self::assertTrue($this->checker->accepts(self::countryByCode('FR'), '20000'), 'Corsica: 2A/2B are département codes, postal codes stay numeric (Ajaccio is 20000).');
        self::assertFalse($this->checker->accepts(self::countryByCode('FR'), '2A'), 'A département code is not a postal code.');
        self::assertFalse($this->checker->accepts(self::countryByCode('FR'), '3100'));
        self::assertFalse($this->checker->accepts(self::countryByCode('FR'), '310000'));
    }

    public function testSwissAndBelgianCodesHaveFourDigits(): void
    {
        self::assertTrue($this->checker->accepts(self::countryByCode('CH'), '1200'));
        self::assertTrue($this->checker->accepts(self::countryByCode('CH'), 'CH-8001'), 'Only the digits are compared.');
        self::assertTrue($this->checker->accepts(self::countryByCode('BE'), '1000'));
        self::assertFalse($this->checker->accepts(self::countryByCode('CH'), '12000'));
        self::assertFalse($this->checker->accepts(self::countryByCode('BE'), '0100'));
    }

    public function testCountryGivenByNameOrEntityIdUsesItsOwnPattern(): void
    {
        // DataTourisme and SowProg send a country name, not an ISO code
        $suisse = new CountryDto();
        $suisse->name = 'Suisse';

        self::assertTrue($this->checker->accepts($suisse, '1200'));
        self::assertFalse($this->checker->accepts($suisse, '12000'));

        // The personal-space form sends an already resolved country
        $resolved = new CountryDto();
        $resolved->entityId = 'BE';

        self::assertTrue($this->checker->accepts($resolved, '1000'));
        self::assertFalse($this->checker->accepts($resolved, '75001'));
    }

    public function testUnverifiableCodesAreRejected(): void
    {
        // Strict policy: without a country, with an unknown one, or with a country whose
        // pattern is not configured yet, a non-empty code cannot be vouched for.
        CountryFactory::createOne(['id' => 'DE', 'name' => 'Allemagne', 'postalCodeRegex' => null]);

        self::assertFalse($this->checker->accepts(null, '31000'));
        self::assertFalse($this->checker->accepts(self::countryByCode('XX'), '31000'));
        self::assertFalse($this->checker->accepts(self::countryByCode('DE'), '10115'));
        self::assertTrue($this->checker->accepts(self::countryByCode('DE'), ''), 'A missing code is still fine.');
    }

    private static function countryByCode(string $code): CountryDto
    {
        $dto = new CountryDto();
        $dto->code = $code;

        return $dto;
    }
}
