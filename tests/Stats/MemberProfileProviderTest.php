<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Stats;

use App\Enum\MemberDistinction;
use App\Factory\CommentFactory;
use App\Factory\EventFactory;
use App\Factory\UserFactory;
use App\Stats\MemberProfileProvider;
use App\Tests\AppKernelTestCase;

final class MemberProfileProviderTest extends AppKernelTestCase
{
    /**
     * The distinctions are earned from the counts of the profile: what the member published and wrote.
     */
    public function testTheDistinctionsComeFromWhatTheMemberGaveTheSite(): void
    {
        $user = UserFactory::createOne();
        $events = EventFactory::createMany(MemberDistinction::ORGANIZER_EVENTS, ['user' => $user]);
        CommentFactory::createMany(MemberDistinction::COMMENTER_COMMENTS, ['user' => $user, 'event' => $events[0]]);
        // Neither published nor approved: they do not count
        EventFactory::createOne(['user' => $user, 'draft' => true]);
        CommentFactory::createOne(['user' => $user, 'event' => $events[0], 'approved' => false]);

        $profile = self::getContainer()->get(MemberProfileProvider::class)->get($user);

        self::assertSame(MemberDistinction::ORGANIZER_EVENTS, $profile->stats->publishedEvents);
        self::assertSame(MemberDistinction::COMMENTER_COMMENTS, $profile->stats->comments);
        self::assertContains(MemberDistinction::Organizer, $profile->distinctions);
        self::assertContains(MemberDistinction::Commenter, $profile->distinctions);
    }

    public function testANewMemberHasNothingYet(): void
    {
        $profile = self::getContainer()->get(MemberProfileProvider::class)->get(UserFactory::createOne());

        self::assertSame(0, $profile->favoriteEvents);
        self::assertSame([], $profile->cities);
        self::assertSame([], $profile->places);
        self::assertNotContains(MemberDistinction::Organizer, $profile->distinctions);
    }
}
