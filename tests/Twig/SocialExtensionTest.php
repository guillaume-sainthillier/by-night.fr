<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Twig;

use App\Twig\SocialExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SocialExtensionTest extends TestCase
{
    #[DataProvider('provideServices')]
    public function testSocialLabelAndIcon(string $service, string $expectedLabel, string $expectedIcon): void
    {
        $extension = new SocialExtension();

        self::assertSame($expectedLabel, $extension->socialLabel($service));
        self::assertSame($expectedIcon, $extension->socialIcon($service));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideServices(): iterable
    {
        yield 'twitter is branded as X' => ['twitter', 'X', 'fa7-brands:x-twitter'];
        yield 'admin twitter is branded as X' => ['twitter_admin', 'X', 'fa7-brands:x-twitter'];
        yield 'facebook' => ['facebook', 'Facebook', 'fa7-brands:facebook'];
        yield 'admin facebook drops the suffix' => ['facebook_admin', 'Facebook', 'fa7-brands:facebook'];
        yield 'google' => ['google', 'Google', 'fa7-brands:google'];
    }
}
