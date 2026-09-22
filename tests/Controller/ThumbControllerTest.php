<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ThumbControllerTest extends WebTestCase
{
    public function testALegacyThumbRedirectsToTheOriginalImage(): void
    {
        $client = self::createClient();

        $client->request('GET', '/thumb/documents/2019/03/15/affiche.jpg');

        self::assertResponseRedirects('http://localhost/documents/2019/03/15/affiche.jpg', Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideForeignTargets(): iterable
    {
        yield 'absolute url' => ['/thumb/https://example.com/x'];
        yield 'absolute url with encoded slashes' => ['/thumb/https:%2F%2Fexample.com/x'];
        yield 'protocol-relative url' => ['/thumb-asset/%2F%2Fexample.com/x'];
        yield 'backslash' => ['/thumb-asset/%2F%5Cexample.com/x'];
        yield 'javascript' => ['/thumb/javascript:alert(1)'];
    }

    #[DataProvider('provideForeignTargets')]
    public function testItNeverRedirectsOutsideTheStorage(string $url): void
    {
        $client = self::createClient();

        $client->request('GET', $url);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
