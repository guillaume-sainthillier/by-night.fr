<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Parser\Toulouse;

use App\Dto\EventDto;
use App\Parser\Toulouse\ToulouseParser;
use App\Tests\AppKernelTestCase;
use ReflectionMethod;

/**
 * The rows of the Open Data file, one by one: a row that cannot be read is left out.
 */
final class ToulouseParserTest extends AppKernelTestCase
{
    public function testEachRowBecomesAnEventAndABadRowIsLeftOut(): void
    {
        $row = static fn (string $id, string $name, string $start): array => [
            $id, $name, '', '', 'Une description de la manifestation.', $start, '2026-10-02', 'À 20h00', '', '',
            'Le Bikini', '', 'Rue Théodore Monod', '', '31520', 'Ramonville-Saint-Agne', 'Concert', 'Musique', 'Jazz, Blues',
            '', '43.55', '1.47', '05 00 00 00 00', 'contact@example.com', 'https://example.com', '', 'Gratuit',
        ];
        $path = tempnam(sys_get_temp_dir(), 'agenda');
        $file = fopen($path, 'w');
        fputcsv($file, ['header'], ';', '"', '"');
        fputcsv($file, $row('1', 'Concert de jazz', '2026-10-01'), ';', '"', '"');
        fputcsv($file, $row('2', 'Date illisible', 'pas une date'), ';', '"', '"');
        fputcsv($file, $row('3', 'Soirée blues', '2026-10-02'), ';', '"', '"');
        fclose($file);
        $parser = self::getContainer()->get(ToulouseParser::class);

        $events = iterator_to_array(new ReflectionMethod(ToulouseParser::class, 'parseCSV')->invoke($parser, $path), false);

        $events = array_values(array_filter($events));
        self::assertSame(['Concert de jazz', 'Soirée blues'], array_map(static fn (EventDto $event): ?string => $event->name, $events));
        self::assertSame('Ramonville-Saint-Agne', $events[0]->place?->city?->name);
        self::assertSame(['Jazz', 'Blues'], array_map(static fn ($tag): ?string => $tag->name, $events[0]->themes));
        self::assertSame(1, $parser->getFailedRecords());
    }
}
