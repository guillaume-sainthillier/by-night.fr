<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests;

use Locale;
use Twig\Environment;

/**
 * The site speaks French only: ICU, and with it Twig's intl filters, format in French without being told.
 */
final class KernelLocaleTest extends AppKernelTestCase
{
    public function testTheIntlFiltersAreFrenchWithoutALocale(): void
    {
        self::assertSame('fr', Locale::getDefault());

        $template = self::getContainer()->get(Environment::class)->createTemplate(
            "{{ '2026-10-24'|format_date(pattern: 'EEEE d MMMM') }} | {{ 1234.5|format_number }}"
        );
        self::assertSame("samedi 24 octobre | 1\u{202f}234,5", $template->render());
    }
}
