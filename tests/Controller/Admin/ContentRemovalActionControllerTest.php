<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Admin;

use App\Enum\ContentRemovalRequestStatus;
use App\Enum\ContentRemovalType;
use App\Factory\ContentRemovalRequestFactory;
use App\Factory\EventFactory;
use App\Factory\UserFactory;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function Zenstruck\Foundry\Persistence\save;

final class ContentRemovalActionControllerTest extends WebTestCase
{
    #[RequiresPhpExtension('mjml')]
    public function testRemovingTheImagesTakesThemDown(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne(['roles' => ['ROLE_ADMIN']]));
        $event = EventFactory::createOne(['url' => 'https://example.test/affiche.png']);
        $path = tempnam(sys_get_temp_dir(), 'png');
        imagepng(imagecreatetruecolor(4, 4), $path);
        $event->setImageSystemFile(new UploadedFile($path, 'affiche.png', 'image/png', null, true));
        save($event);
        self::assertNotNull($event->getImageSystem()->getName());
        $request = ContentRemovalRequestFactory::createOne(['type' => ContentRemovalType::Image, 'events' => [$event]]);

        $client->request('GET', \sprintf('/_administration/content-removal-action/%d/remove-images', $request->getId()));

        self::assertResponseRedirects('/_administration/content-removal-request');
        $event = EventFactory::find(['id' => $event->getId()]);
        self::assertNull($event->getImageSystem()->getName());
        self::assertNotNull($event->getImageRemovedAt());
        self::assertSame(ContentRemovalRequestStatus::Processed, ContentRemovalRequestFactory::find(['id' => $request->getId()])->getStatus());
    }
}
