<?php

namespace TBN\MajDataBundle\Tests\Utils;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\MajDataBundle\Entity\Exploration;
use TBN\MajDataBundle\Reject\Reject;
use TBN\MajDataBundle\Utils\Firewall;

class FirewallTest extends KernelTestCase
{
    /**
     * @var Firewall
     */
    protected $firewall;

    public function setUp()
    {
        self::bootKernel();

        $this->firewall = static::$kernel->getContainer()->get('tbn.firewall');
    }

    public function testExplorations()
    {
        $noNeedToUpdateReject = (new Reject())->addReason(Reject::NO_NEED_TO_UPDATE);
        $badReject = (new Reject())->addReason(Reject::BAD_PLACE_LOCATION);
        $deletedReject = (new Reject())->addReason(Reject::EVENT_DELETED);
        $validReject = (new Reject());

        $now = new \DateTime();

        //L'événément ne doit pas être valide car il n'a pas changé
        $exploration = (new Exploration())->setReject(clone $noNeedToUpdateReject)->setLastUpdated($now)->setFirewallVersion(Firewall::VERSION);
        $event = (new Agenda())->setFbDateModification($now);
        $this->firewall->filterEventExploration($exploration, $event);
        $this->assertEquals($exploration->getReject()->getReason(), $noNeedToUpdateReject->getReason());

        //L'événement doit être valide car il a été mis à jour
        $exploration = (new Exploration())->setReject(clone $noNeedToUpdateReject)->setLastUpdated($now)->setFirewallVersion(Firewall::VERSION);
        $tomorrow = clone $now;
        $tomorrow->modify('+1 day');
        $event = (new Agenda())->setFbDateModification($tomorrow);
        $this->firewall->filterEventExploration($exploration, $event);
        $this->assertEquals($exploration->getReject()->getReason(), $validReject->getReason());

        //L'événement ne doit être valide car la version du firewall a changé mais qu'il n'a pas changé
        $exploration = (new Exploration())->setReject(clone $noNeedToUpdateReject)->setLastUpdated($now)->setFirewallVersion('old version');
        $event = (new Agenda())->setFbDateModification($now);
        $this->firewall->filterEventExploration($exploration, $event);
        $this->assertEquals($exploration->getReject()->getReason(), $noNeedToUpdateReject->getReason());
        $this->assertEquals($exploration->getFirewallVersion(), Firewall::VERSION);

        //L'événement doit être valide car la version du firewall a changé et qu'il n'était pas valide avant
        $exploration = (new Exploration())->setReject($badReject)->setLastUpdated($now)->setFirewallVersion('old version');
        $event = (new Agenda())->setReject(new Reject());
        $this->firewall->filterEventExploration($exploration, $event);
        $this->assertEquals($exploration->getReject()->getReason(), $validReject->getReason());
        $this->assertEquals($exploration->getFirewallVersion(), Firewall::VERSION);

        //L'événément ne doit pas être mis à jour car son créateur l'a supprimé
        $exploration = (new Exploration())->setReject(clone $deletedReject)->setLastUpdated($now)->setFirewallVersion('old version');
        $event = (new Agenda())->setReject(new Reject());
        $this->firewall->filterEventExploration($exploration, $event);
        $this->assertEquals($exploration->getReject()->getReason(), $deletedReject->getReason());
    }
}
