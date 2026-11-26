<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Model;

use App\Api\ApiResource\CityAutocomplete;

/**
 * The answer of the MCP tool find_cities: the cities of GET /api/cities.
 */
final readonly class CitySearchResult
{
    public function __construct(
        /** @var list<CityAutocomplete> the most populated first */
        public array $cities,
    ) {
    }
}
