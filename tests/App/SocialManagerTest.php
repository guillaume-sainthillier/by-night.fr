<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\App;

use App\App\SocialManager;
use App\Factory\AppOAuthFactory;
use App\Tests\AppKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class SocialManagerTest extends AppKernelTestCase
{
    public function testTheSiteAccountsAreCreatedTheFirstTimeOnly(): void
    {
        $socialManager = self::getContainer()->get(SocialManager::class);

        $created = $socialManager->getOrCreateAppOAuth();
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        self::assertSame(1, AppOAuthFactory::count());
        self::assertSame($created, $socialManager->getOrCreateAppOAuth());
    }

    public function testTheExistingSiteAccountsAreKept(): void
    {
        $existing = AppOAuthFactory::createOne();

        self::assertSame($existing->getId(), self::getContainer()->get(SocialManager::class)->getOrCreateAppOAuth()->getId());
        self::assertSame(1, AppOAuthFactory::count());
    }
}
