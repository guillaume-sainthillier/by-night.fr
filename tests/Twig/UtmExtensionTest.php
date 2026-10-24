<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Twig;

use App\Twig\UtmExtension;
use App\Utils\EmailUtm;
use PHPUnit\Framework\TestCase;

final class UtmExtensionTest extends TestCase
{
    public function testTheLinksOfACampaignAreTagged(): void
    {
        $context = ['utm' => EmailUtm::campaign('publisher-news', '2026-10')];

        self::assertSame(
            'https://by-night.test/espace-perso/mes-soirees?utm_source=publisher-news&utm_medium=email&utm_campaign=2026-10&utm_content=cta',
            new UtmExtension()->utm($context, 'https://by-night.test/espace-perso/mes-soirees', 'cta'),
        );
        self::assertSame(
            'https://by-night.test/a-propos?utm_source=publisher-news&utm_medium=email&utm_campaign=2026-10',
            new UtmExtension()->utm($context, 'https://by-night.test/a-propos'),
        );
    }

    public function testTheLinksOfAnotherEmailStayAsTheyAre(): void
    {
        self::assertSame('https://by-night.test/a-propos', new UtmExtension()->utm([], 'https://by-night.test/a-propos', 'footer'));
    }
}
