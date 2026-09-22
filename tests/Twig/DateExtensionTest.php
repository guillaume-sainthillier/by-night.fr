<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Twig;

use App\Twig\DateExtension;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateExtensionTest extends TestCase
{
    #[DataProvider('provideDates')]
    public function testDiffDate(string $modifier, string $expected): void
    {
        self::assertSame($expected, $this->diffDate($modifier));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideDates(): iterable
    {
        yield 'one day is singular' => ['-1 day -1 minute', 'Il y a 1 jour'];
        yield 'days' => ['-3 days -1 minute', 'Il y a 3 jours'];
        yield 'one year' => ['-1 year -1 day', 'Il y a 1 an'];
        yield 'years' => ['-4 years -1 day', 'Il y a 4 ans'];
        yield 'months are invariable' => ['-2 months -1 day', 'Il y a 2 mois'];
        yield 'one hour' => ['-1 hour -1 minute', 'Il y a 1 heure'];
        yield 'minutes' => ['-5 minutes -1 second', 'Il y a 5 minutes'];
        yield 'a few seconds ago' => ['-10 seconds', "À l'instant"];
    }

    public function testSeconds(): void
    {
        self::assertMatchesRegularExpression('/^Il y a 4[56] secondes$/', $this->diffDate('-45 seconds'));
    }

    /**
     * ICU separates some counts from their unit with a no-break space, depending on the unit and the CLDR version.
     */
    private function diffDate(string $modifier): string
    {
        return (string) preg_replace('/\h/u', ' ', new DateExtension()->diffDate(new DateTimeImmutable($modifier)));
    }
}
