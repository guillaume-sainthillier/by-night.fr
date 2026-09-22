<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Parser;

use App\Dto\EventDto;
use App\Entity\Event;
use App\Parser\Common\BilletsReducAwinParser;
use App\Parser\Common\CDiscountAwinParser;
use App\Parser\Common\FnacSpectaclesAwinParser;
use App\Parser\Common\OpenAgendaParser;
use App\Parser\Common\SeeTicketsKwankoParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AffiliateParsersTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideParsers(): iterable
    {
        yield 'Fnac' => [FnacSpectaclesAwinParser::getParserName(), true];
        yield 'SeeTickets' => [SeeTicketsKwankoParser::getParserName(), true];
        yield 'BilletsReduc' => [BilletsReducAwinParser::getParserName(), true];
        yield 'CDiscount' => [CDiscountAwinParser::getParserName(), true];
        yield 'OpenAgenda' => [OpenAgendaParser::getParserName(), false];
    }

    #[DataProvider('provideParsers')]
    public function testTheEventAndItsDtoAgree(string $parserName, bool $affiliate): void
    {
        $dto = new EventDto();
        $dto->parserName = $parserName;
        $event = new Event()->setFromData($parserName);

        self::assertSame($affiliate, $dto->isAffiliate(), 'The import Firewall reads the DTO');
        self::assertSame($affiliate, $event->isAffiliate(), 'The event page reads the entity');
    }
}
