<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Tests\AppKernelTestCase;
use App\Utils\SluggerUtils;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;

final class SluggerUtilsTest extends AppKernelTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        // Initialize the SluggerUtils with the container's slugger
        self::getContainer()->get(SluggerUtils::class);
    }

    #[DataProvider('slugProvider')]
    public function testGenerateSlug(string $input, string $expected): void
    {
        $result = SluggerUtils::generateSlug($input);

        self::assertEquals($expected, $result);
    }

    public static function slugProvider(): array
    {
        return [
            // Basic text
            ['Hello World', 'hello-world'],

            // Accents removed
            ['café', 'cafe'],
            ['élève', 'eleve'],
            ['Zürich', 'zurich'],

            // Multiple words
            ['The Quick Brown Fox', 'the-quick-brown-fox'],

            // Special characters
            ['Hello & World!', 'hello-world'],
            ['user@email.com', 'user-email-com'],

            // Numbers preserved
            ['Test 123', 'test-123'],
            ['2024 Event', '2024-event'],

            // Multiple spaces
            ['Hello    World', 'hello-world'],

            // Leading/trailing spaces
            ['  Hello World  ', 'hello-world'],

            // Hyphens
            ['Already-Slugged', 'already-slugged'],

            // Underscores
            ['Snake_Case_Text', 'snake-case-text'],

            // French
            ['Événement à Paris', 'evenement-a-paris'],

            // German
            ['Müller Straße', 'muller-strasse'],

            // Spanish
            ['Mañana en España', 'manana-en-espana'],
        ];
    }

    public function testGenerateSlugIsLowercase(): void
    {
        $result = SluggerUtils::generateSlug('HELLO WORLD');

        self::assertEquals('hello-world', $result);
        self::assertEquals(strtolower($result), $result);
    }

    public function testGenerateSlugWithEmptyString(): void
    {
        $result = SluggerUtils::generateSlug('');

        self::assertEquals('', $result);
    }

    public function testGenerateSlugWithOnlySpecialCharacters(): void
    {
        $result = SluggerUtils::generateSlug('!@#$%^&*()');

        // Result should be a valid slug (possibly empty)
        self::assertMatchesRegularExpression('/^[a-z0-9\-]*$/', $result);
    }

    public function testGenerateSlugConsistency(): void
    {
        $input = 'Test Event 2024';
        $result1 = SluggerUtils::generateSlug($input);
        $result2 = SluggerUtils::generateSlug($input);

        self::assertEquals($result1, $result2, 'Same input should always produce same slug');
    }

    public function testGenerateSlugUrlSafe(): void
    {
        $result = SluggerUtils::generateSlug('Hello World! & More...');

        // Check that result contains only URL-safe characters
        self::assertMatchesRegularExpression('/^[a-z0-9\-]*$/', $result);
    }

    public function testGenerateSlugNoConsecutiveHyphens(): void
    {
        $result = SluggerUtils::generateSlug('Hello   -   World');

        self::assertStringNotContainsString('--', $result);
        self::assertStringNotContainsString('---', $result);
    }

    public function testGenerateSlugNoLeadingTrailingHyphens(): void
    {
        $result = SluggerUtils::generateSlug('  Hello World  ');

        self::assertStringStartsNotWith('-', $result);
        self::assertStringEndsNotWith('-', $result);
    }
}
