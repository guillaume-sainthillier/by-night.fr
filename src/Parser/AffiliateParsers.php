<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Parser;

use App\Parser\Common\BilletsReducAwinParser;
use App\Parser\Common\CDiscountAwinParser;
use App\Parser\Common\FnacSpectaclesAwinParser;
use App\Parser\Common\SeeTicketsKwankoParser;

/**
 * The ticketing feeds: their products are shown with an affiliate link and pass the import
 * Firewall without its name, description and spam checks. One list for the entity and the DTO.
 */
final class AffiliateParsers
{
    public static function isAffiliate(?string $parserName): bool
    {
        return \in_array($parserName, self::all(), true);
    }

    /**
     * How well its page shows an event, the best first, when several sources import it (EventFamilyResolver elects
     * the canonical on it): 0 for the agendas and the organizers, who write a description, then the feeds in order.
     */
    public static function pageRank(?string $parserName): int
    {
        $index = array_search($parserName, self::all(), true);

        return false === $index ? 0 : $index + 1;
    }

    /**
     * The feeds, the one whose page shows an event best first: Fnac and BilletsReduc title their shows as they are
     * spelled, SeeTickets in capitals, CDiscount in lower case ("Claudio capeo").
     *
     * @return list<string>
     */
    private static function all(): array
    {
        return [
            FnacSpectaclesAwinParser::getParserName(),
            BilletsReducAwinParser::getParserName(),
            SeeTicketsKwankoParser::getParserName(),
            CDiscountAwinParser::getParserName(),
        ];
    }
}
