<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Routing;

use App\Enum\AgendaType;
use Stringable;

/**
 * The route requirement of the {typeSlug} of a type page: one of the French slugs of AgendaType, not its values
 * (EnumRequirement).
 */
final readonly class AgendaTypeSlugRequirement implements Stringable
{
    public function __toString(): string
    {
        return implode('|', array_map(static fn (AgendaType $type): string => preg_quote($type->getSlug(), '#'), AgendaType::cases()));
    }
}
