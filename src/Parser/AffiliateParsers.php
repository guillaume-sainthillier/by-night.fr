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
        return \in_array($parserName, [
            FnacSpectaclesAwinParser::getParserName(),
            SeeTicketsKwankoParser::getParserName(),
            BilletsReducAwinParser::getParserName(),
            CDiscountAwinParser::getParserName(),
        ], true);
    }
}
