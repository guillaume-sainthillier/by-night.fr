<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Handler;

use App\Handler\ReservationsHandler;
use App\Tests\AppKernelTestCase;

class ReservationsHandlerTest extends AppKernelTestCase
{
    private ReservationsHandler $reservationsHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reservationsHandler = self::getContainer()->get(ReservationsHandler::class);
    }

    /**
     * @dataProvider getReservationsSample
     */
    public function testParseReservations(string $content, array $expectedInfos)
    {
        $infos = $this->reservationsHandler->parseReservations($content);
        self::assertEquals($expectedInfos, $infos);
    }

    public function getReservationsSample(): iterable
    {
        yield [
            ' 02 40 60 02 80 , 02 40 60 88 28 ',
            [
                'urls' => null,
                'phones' => ['02 40 60 02 80', '02 40 60 88 28'],
                'emails' => null,
            ],
        ];

        yield [
            ' 02 40 60 02 80 ',
            [
                'urls' => null,
                'phones' => ['02 40 60 02 80'],
                'emails' => null,
            ],
        ];

        // Website without protocol
        yield [
            'www.fnac.com www.sallenougaro.com',
            [
                'urls' => ['www.fnac.com', 'www.sallenougaro.com'],
                'phones' => null,
                'emails' => null,
            ],
        ];

        yield [
            '02 40 60 02 80, 02 40 60 88 28, organisation@labaule-cheval.com, http://www.labaule-cheval.com, https://www.labaule-guerande.com/annule-longines-fei-jumping-nations-cup-de-france-la-baule-jumpinginternational2020.html',
            [
                'urls' => ['http://www.labaule-cheval.com', 'https://www.labaule-guerande.com/annule-longines-fei-jumping-nations-cup-de-france-la-baule-jumpinginternational2020.html'],
                'phones' => ['02 40 60 02 80', '02 40 60 88 28'],
                'emails' => ['organisation@labaule-cheval.com'],
            ],
        ];

        yield [
            '02 40 60 02 80 organisation@labaule-cheval.com http://www.labaule-cheval.com https://www.labaule-guerande.com/annule-longines-fei-jumping-nations-cup-de-france-la-baule-jumpinginternational2020.html',
            [
                'urls' => ['http://www.labaule-cheval.com', 'https://www.labaule-guerande.com/annule-longines-fei-jumping-nations-cup-de-france-la-baule-jumpinginternational2020.html'],
                'phones' => ['02 40 60 02 80'],
                'emails' => ['organisation@labaule-cheval.com'],
            ],
        ];
    }
}
