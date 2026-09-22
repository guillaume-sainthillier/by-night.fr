<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\EntityProvider;

use App\Dto\CountryDto;
use App\EntityProvider\CountryEntityProvider;
use App\Factory\CountryFactory;
use App\Tests\AppKernelTestCase;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Country rows are only ever matched, never created: a DTO that does not resolve here
 * silently yields a place without country. Feed values must therefore match the row
 * whatever their case or padding, and on every database (SQLite compares ids
 * case-sensitively, MySQL does not).
 */
#[ResetDatabase]
final class CountryEntityProviderTest extends AppKernelTestCase
{
    private CountryEntityProvider $provider;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = self::getContainer()->get(CountryEntityProvider::class);
        $this->provider->clear();

        CountryFactory::france()->create();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideMatchingCodes(): iterable
    {
        yield 'canonical' => ['FR'];
        yield 'lower-cased' => ['fr'];
        yield 'mixed case' => ['Fr'];
        yield 'padded' => [' FR '];
    }

    #[DataProvider('provideMatchingCodes')]
    public function testCodeResolvesWhateverItsCaseOrPadding(string $code): void
    {
        $dto = new CountryDto();
        $dto->code = $code;

        $this->provider->prefetchEntities([$dto], false);

        self::assertSame('FR', $this->provider->getEntity($dto)?->getId());
    }

    public function testNameStillResolves(): void
    {
        // DataTourisme and SowProg send a name, not a code
        $dto = new CountryDto();
        $dto->name = 'France';

        $this->provider->prefetchEntities([$dto], false);

        self::assertSame('FR', $this->provider->getEntity($dto)?->getId());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnresolvableCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['  '];
        yield 'unknown country' => ['XX'];
    }

    #[DataProvider('provideUnresolvableCodes')]
    public function testUnresolvableCodeYieldsNothing(string $code): void
    {
        $dto = new CountryDto();
        $dto->code = $code;

        $this->provider->prefetchEntities([$dto], false);

        self::assertNull($this->provider->getEntity($dto));
    }
}
