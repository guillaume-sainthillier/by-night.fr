<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * The URLs of the countries carried a "c--" prefix: each former one reaches the new one in a single redirect.
 */
final class LegacyCountryUrlSubscriberTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideFormerUrls(): iterable
    {
        yield 'country page' => ['/c--france', '/france'];
        yield 'with its trailing slash' => ['/c--france/', '/france'];
        yield 'former agenda' => ['/c--france/agenda', '/france'];
        yield 'former agenda page, with its filters' => ['/c--france/agenda/2?when=this_weekend&price=free', '/france/2?when=this_weekend&price=free'];
        yield 'type page' => ['/c--suisse/agenda/sortir/concert', '/suisse/agenda/sortir/concert'];
        yield 'event page' => ['/c--france/soiree/fete-des-sardines--2683463', '/france/soiree/fete-des-sardines--2683463'];
    }

    #[DataProvider('provideFormerUrls')]
    public function testAFormerUrlRedirectsOnceToTheNewOne(string $formerUrl, string $url): void
    {
        $client = self::createClient();

        $client->request('GET', $formerUrl);

        self::assertResponseRedirects($url, Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testACitySlugStartingLikeACountryOneIsNoFormerUrl(): void
    {
        $client = self::createClient();

        $client->request('GET', '/c-france');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
