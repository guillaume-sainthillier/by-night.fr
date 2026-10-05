<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Storage;

use App\Storage\UploadMappings;
use App\Tests\AppKernelTestCase;

final class UploadMappingsTest extends AppKernelTestCase
{
    public function testTheFoldersAndColumnsComeFromTheVichMappings(): void
    {
        $mappings = self::getContainer()->get(UploadMappings::class);

        self::assertEqualsCanonicalizing(
            ['uploads/users', 'uploads/documents', 'uploads/pages', 'uploads/countries', 'uploads/cities'],
            $mappings->getFolders(),
        );
        // A city is an admin zone (single table inheritance): its cover is named in that table
        self::assertEqualsCanonicalizing([
            ['table' => 'event', 'column' => 'image_name'],
            ['table' => 'event', 'column' => 'image_system_name'],
            ['table' => 'user', 'column' => 'image_name'],
            ['table' => 'user', 'column' => 'image_system_name'],
            ['table' => 'page', 'column' => 'image_name'],
            ['table' => 'country', 'column' => 'hero_image_name'],
            ['table' => 'admin_zone', 'column' => 'hero_image_name'],
        ], $mappings->getNameColumns());
    }

    public function testAFileIsLocatedInItsMapping(): void
    {
        $mappings = self::getContainer()->get(UploadMappings::class);

        self::assertSame(['mapping' => 'event_image', 'uriPrefix' => '/uploads/documents', 'path' => '2026/06/12/a.jpg'], $mappings->locate('uploads/documents/2026/06/12/a.jpg'));
        self::assertSame(['mapping' => 'country_image', 'uriPrefix' => '/uploads/countries', 'path' => 'a.jpg'], $mappings->locate('uploads/countries/a.jpg'));
        self::assertNull($mappings->locate('uploads/old/a.jpg'));
        self::assertNull($mappings->locate('uploads/documentsbis/a.jpg'));
    }
}
