<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Enum;

use App\Enum\AgendaType;
use App\Routing\AgendaTypeSlugRequirement;
use PHPUnit\Framework\TestCase;

final class AgendaTypeTest extends TestCase
{
    /**
     * The path of a type page keeps the French slug the search engines know; its value, in the query strings, is English.
     */
    public function testATypeIsFoundByTheSlugOfItsPageNotByItsValue(): void
    {
        self::assertSame(AgendaType::Student, AgendaType::fromSlug('etudiant'));
        self::assertSame('student', AgendaType::Student->value);
        self::assertNull(AgendaType::fromSlug('student'));
    }

    public function testTheRouteRequirementIsTheSlugs(): void
    {
        self::assertSame('concert|spectacle|exposition|famille|etudiant', (string) new AgendaTypeSlugRequirement());
    }
}
