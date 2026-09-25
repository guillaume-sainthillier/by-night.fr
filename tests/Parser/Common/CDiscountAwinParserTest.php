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
use App\Parser\Common\CDiscountAwinParser;
use App\Tests\AppKernelTestCase;
use Override;
use ReflectionMethod;

/**
 * One row per performance, dated in custom_1 as "le 14/02/2027 à 20h" (whole hours only
 * in the feed of 2026-09-24).
 */
final class CDiscountAwinParserTest extends AppKernelTestCase
{
    private CDiscountAwinParser $parser;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = self::getContainer()->get(CDiscountAwinParser::class);
    }

    public function testARowMapsToAnEvent(): void
    {
        $event = $this->invoke(self::row());

        self::assertInstanceOf(EventDto::class, $event);
        self::assertSame('CDiscount', $event->fromData);
        self::assertSame('46008799592', $event->externalId);
        self::assertSame('Nico Moreno', $event->name);
        self::assertSame('2027-02-14 00:00', $event->startDate?->format('Y-m-d H:i'));
        self::assertSame('2027-02-14 00:00', $event->endDate?->format('Y-m-d H:i'));
        self::assertSame('À 20h', $event->hours);
        self::assertSame('39.9€', $event->prices);
        self::assertSame('Zénith', $event->place?->name);
        self::assertSame('11 avenue Raymond Badiou', $event->place->street);
        self::assertSame('Toulouse', $event->place->city?->name);
        self::assertSame('31300', $event->place->city->postalCode);
        self::assertSame('FR', $event->place->country?->code);
    }

    public function testTheSamePlaceGetsTheSameExternalId(): void
    {
        $first = $this->invoke(self::row());
        $second = $this->invoke(self::row(['merchant_product_id' => '46008799593', 'custom_1' => 'le 15/02/2027 à 18h']));

        self::assertNotNull($first?->place?->externalId);
        self::assertSame($first->place->externalId, $second?->place?->externalId);
    }

    public function testRowsThatCannotBeDatedOrPlacedAreLeftOut(): void
    {
        self::assertNull($this->invoke(self::row(['custom_6' => ''])), 'No venue');
        self::assertNull($this->invoke(self::row(['custom_1' => ''])), 'No date');
        self::assertNull($this->invoke(self::row(['custom_1' => 'Date à venir'])), 'No parsable date');
    }

    private function invoke(array $row): ?EventDto
    {
        /** @var EventDto|null $event */
        $event = new ReflectionMethod(CDiscountAwinParser::class, 'arrayToDto')->invoke($this->parser, $row);

        return $event;
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private static function row(array $overrides = []): array
    {
        return array_replace([
            'aw_deep_link' => 'https://www.awin1.com/pclick.php?p=46008799592&a=660995&m=19627',
            'aw_image_url' => 'https://images2.productserve.com/?w=200&h=200&url=a.jpg',
            'merchant_product_id' => '46008799592',
            'product_name' => 'Nico Moreno',
            'description' => 'Techno.',
            'search_price' => '39.9',
            'custom_1' => 'le 14/02/2027 à 20h',
            'custom_2' => 'Toulouse',
            'custom_3' => '31300',
            'custom_4' => '11 avenue Raymond Badiou',
            'custom_6' => 'Zénith',
        ], $overrides);
    }
}
