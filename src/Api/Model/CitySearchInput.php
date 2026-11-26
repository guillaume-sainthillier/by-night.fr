<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Model;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * What GET /api/cities (the autocomplete of the site's city fields) and the MCP tool find_cities take.
 */
final readonly class CitySearchInput
{
    public function __construct(
        /** A name or a postal code: "Toulouse", "Saint-Étienne", "31000". */
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $q,
    ) {
    }
}
