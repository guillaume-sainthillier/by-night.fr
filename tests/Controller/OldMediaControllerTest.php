<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller;

use App\Factory\EventFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;
use Vich\UploaderBundle\Entity\File as EmbeddedFile;

final class OldMediaControllerTest extends AppWebTestCase
{
    public function testRedirectsALegacyEventImageUrlToItsOriginAndLetsTheCdnKeepIt(): void
    {
        $client = self::createClient();
        EventFactory::createOne(['imageSystem' => $this->file('5aa78efdc33f4482315163.jpg')]);

        $client->request('GET', '/media/cache/thumb/documents/5aa78efdc33f4482315163.jpg');

        self::assertResponseStatusCodeSame(301);
        self::assertMatchesRegularExpression('#^http://localhost/uploads/documents/\d{4}/\d{2}/\d{2}/5aa78efdc33f4482315163\.jpg$#', (string) $client->getResponse()->headers->get('Location'));
        self::assertResponseHeaderSame('Cache-Control', 'max-age=2592000, public');
    }

    public function testRedirectsALegacyUserImageUrlToItsOrigin(): void
    {
        $client = self::createClient();
        UserFactory::createOne(['image' => $this->file('5aa78efdc33f4482315164.png')]);

        $client->request('GET', '/uploads/users/5aa78efdc33f4482315164.png');

        self::assertResponseStatusCodeSame(301);
        self::assertMatchesRegularExpression('#^http://localhost/uploads/users/\d{4}/\d{2}/\d{2}/5aa78efdc33f4482315164\.png$#', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testUnknownImageReturns404(): void
    {
        $client = self::createClient();

        $client->request('GET', '/uploads/documents/unknown.jpg');

        self::assertResponseStatusCodeSame(404);
    }

    private function file(string $name): EmbeddedFile
    {
        $file = new EmbeddedFile();
        $file->setName($name);

        return $file;
    }
}
