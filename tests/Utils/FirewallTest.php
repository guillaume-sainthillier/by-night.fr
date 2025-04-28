<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Dto\EventDto;
use App\Entity\ParserData;
use App\Reject\Reject;
use App\Tests\AppKernelTestCase;
use App\Utils\Firewall;
use DateTime;

class FirewallTest extends AppKernelTestCase
{
    protected ?Firewall $firewall = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->firewall = static::getContainer()->get(Firewall::class);
    }

    public function testExplorations(): void
    {
        $noNeedToUpdateReject = (new Reject())->addReason(Reject::NO_NEED_TO_UPDATE);
        $badReject = (new Reject())->addReason(Reject::BAD_PLACE_LOCATION);
        $deletedReject = (new Reject())->addReason(Reject::EVENT_DELETED);

        $now = new DateTime();
        $tomorrow = new DateTime('tomorrow');

        // L'événement ne doit pas être valide car il n'a pas changé
        $exploration = (new ParserData())->setReject(clone $noNeedToUpdateReject)->setLastUpdated($now);
        $event = (new EventDto());
        $event->externalUpdatedAt = $now;

        $this->firewall->filterEventExploration($exploration, $event);
        self::assertEquals(Reject::NO_NEED_TO_UPDATE | Reject::VALID, $exploration->getReject()->getReason());
        self::assertEquals(Firewall::VERSION, $exploration->getFirewallVersion());

        // L'événement doit être valide car il a été mis à jour
        $exploration = (new ParserData())->setReject(clone $noNeedToUpdateReject)->setLastUpdated($now);
        $event = (new EventDto());
        $event->externalUpdatedAt = $tomorrow;

        $this->firewall->filterEventExploration($exploration, $event);
        self::assertEquals(Reject::VALID, $exploration->getReject()->getReason());
        self::assertEquals(Firewall::VERSION, $exploration->getFirewallVersion());

        // L'événement ne doit pas être valide car la version du firewall a changé mais qu'il n'a pas changé
        $exploration = (new ParserData())->setReject(clone $noNeedToUpdateReject)->setLastUpdated($now)->setFirewallVersion('old version');
        $event = (new EventDto());
        $event->externalUpdatedAt = $now;

        $this->firewall->filterEventExploration($exploration, $event);
        self::assertEquals(Reject::NO_NEED_TO_UPDATE | Reject::VALID, $exploration->getReject()->getReason());
        self::assertEquals(Firewall::VERSION, $exploration->getFirewallVersion());

        // L'événement doit être valide car la version du firewall a changé et qu'il n'était pas valide avant
        $exploration = (new ParserData())->setReject($badReject)->setLastUpdated($now)->setFirewallVersion('old version');
        $event = (new EventDto());
        $event->reject = new Reject();

        $this->firewall->filterEventExploration($exploration, $event);
        self::assertEquals(Reject::VALID, $exploration->getReject()->getReason());
        self::assertEquals(Firewall::VERSION, $exploration->getFirewallVersion());

        // L'événement ne doit pas être mis à jour car son créateur l'a supprimé
        $exploration = (new ParserData())->setReject(clone $deletedReject)->setLastUpdated($now)->setFirewallVersion('old version');
        $event = (new EventDto());
        $event->reject = new Reject();
        $event->parserVersion = 'new version';

        $this->firewall->filterEventExploration($exploration, $event);
        self::assertEquals(Reject::EVENT_DELETED | Reject::VALID, $exploration->getReject()->getReason());
        self::assertEquals('old version', $exploration->getFirewallVersion());
    }
}
