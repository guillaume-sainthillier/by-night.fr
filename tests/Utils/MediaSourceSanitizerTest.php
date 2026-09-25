<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Utils\HtmlFormatter;
use App\Utils\MediaSourceSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Descriptions come from members and feeds: a frame only keeps a source from a known player,
 * as the browser will resolve it, whatever the markup looks like.
 *
 * @see MediaSourceSanitizer
 */
final class MediaSourceSanitizerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideAcceptedFrames(): iterable
    {
        yield 'protocol-relative youtube' => ['//www.youtube.com/embed/abc', 'https://www.youtube.com/embed/abc'];
        yield 'http vimeo' => ['http://player.vimeo.com/video/1', 'https://player.vimeo.com/video/1'];
        yield 'upper-cased scheme and host' => ['HTTP://WWW.YOUTUBE.COM/embed/abc', 'https://WWW.YOUTUBE.COM/embed/abc'];
        yield 'bare player host' => ['https://youtube-nocookie.com/embed/abc', 'https://youtube-nocookie.com/embed/abc'];
        yield 'google maps' => ['https://www.google.com/maps/embed?pb=1', 'https://www.google.com/maps/embed?pb=1'];
        yield 'trailing spaces' => ['https://www.dailymotion.com/embed/video/x1  ', 'https://www.dailymotion.com/embed/video/x1'];
    }

    #[DataProvider('provideAcceptedFrames')]
    public function testAFrameOfAKnownPlayerIsKept(string $src, string $expected): void
    {
        self::assertSame($expected, $this->frameSource($src));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideRefusedFrames(): iterable
    {
        yield 'unknown host' => ['https://evil.example/embed'];
        yield 'player name as a subdomain' => ['https://youtube.com.evil.example/embed'];
        yield 'player name as a suffix' => ['https://notyoutube.com/embed'];
        yield 'player host as user info' => ['https://www.youtube.com@evil.example/embed'];
        yield 'player host after a backslash' => ['https://evil.example\@www.youtube.com/embed'];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'data' => ['data:text/html,<script>alert(1)</script>'];
        yield 'relative' => ['/embed/abc'];
    }

    #[DataProvider('provideRefusedFrames')]
    public function testAFrameOfAnyOtherSourceLosesIt(string $src): void
    {
        self::assertNull($this->frameSource($src));
    }

    public function testAProtocolRelativeImageGetsHttps(): void
    {
        self::assertStringContainsString(
            'src="https://cdn.example.com/a.jpg"',
            new HtmlFormatter()->format('<img src="//cdn.example.com/a.jpg" alt="" />'),
        );
    }

    public function testAnImageFromAnyHostIsKept(): void
    {
        self::assertStringContainsString(
            'src="http://images.example.com/a.jpg"',
            new HtmlFormatter()->format('<img src="http://images.example.com/a.jpg" alt="" />'),
        );
    }

    private function frameSource(string $src): ?string
    {
        $html = new HtmlFormatter()->format(\sprintf('<iframe src="%s"></iframe>', htmlspecialchars($src, \ENT_QUOTES)));
        if (1 !== preg_match('~<iframe[^>]*\ssrc="([^"]*)"~', $html, $matches)) {
            return null;
        }

        return html_entity_decode($matches[1], \ENT_QUOTES);
    }
}
