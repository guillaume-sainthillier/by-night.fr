<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Routing;

use App\Enum\DateRangePreset;
use Stringable;

/**
 * The route requirement of the {dateSlug} of a date page: the page slugs of DateRangePreset ("ce-week-end").
 */
final readonly class DatePageSlugRequirement implements Stringable
{
    public function __toString(): string
    {
        $slugs = array_filter(array_map(static fn (DateRangePreset $preset): ?string => $preset->getPageSlug(), DateRangePreset::cases()));

        return implode('|', array_map(static fn (string $slug): string => preg_quote($slug, '#'), $slugs));
    }
}
