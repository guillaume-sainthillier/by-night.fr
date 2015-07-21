<?php

namespace TBN\MajDataBundle\Tests\Utils;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use TBN\AgendaBundle\Entity\Place;
use TBN\AgendaBundle\Entity\Ville;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\MainBundle\Entity\Site;

class ComparatorTest extends KernelTestCase {

    protected $container;
    protected $em;

    public function setUp() {
	
	static::$kernel = static::createKernel();
	static::$kernel->boot();

	$this->container = static::$kernel->getContainer();
	$this->em = static::$kernel->getContainer()
		->get('doctrine')
		->getManager();
    }

    protected function tearDown()
    {
	$this->em->close();
	parent::tearDown();	
    }

    public function testEvents() {
	$handler    = $this->container->get('tbn.event_handler');
	$now	    = new \DateTime;

	$toulouse   = (new Site)
		->setNom('Toulouse')
		->setLatitude(43.6)
		->setLongitude(1.43333)
		->setDistanceMax(0.2);

	$events	    = [
	    //Lieu trop éloigné
	    (new Agenda)
		->setNom('Dumb event')
		->setDescriptif('Allowed decision')
		->setPlace((new Place)
			->setNom('Les Frères Ibarboure')
			->setLatitude(43.418123325064)
			->setLongitude(-1.5979993810236)
			->setVille((new Ville)
				->setNom('Bidart')
				->setCodePostal('64210')
				->setSite($toulouse)
			)
			->setSite($toulouse)
		)
		->setTrustedLocation(false)
		->setSite($toulouse)
	    ,
	    //Pas de description
	    (new Agenda)
		->setNom('RNPI - Toulouse')
		->setPlace((new Place)
			->setNom('Toulouse')
			->setLatitude(43.6)
			->setLongitude(1.43333)
			->setVille((new Ville)
				->setNom('Toulouse')
				->setSite($toulouse)
			)
			->setSite($toulouse)
		)
		->setTrustedLocation(false)
		->setSite($toulouse)
	    ,
	    //Pas de description
	    (new Agenda)
		->setNom('Formation initiateur sonmudo')
		->setDescriptif('9 et 10 mai à st léon (31)9h-17h 90 € le WE ou contact pour plus d\'infos fredsonmudo@gmail.com')
		->setPlace((new Place)
			->setNom('Saint-Léon')
			->setLatitude(43.400149585)
			->setLongitude(1.563844585)
			->setVille((new Ville)
				->setCodePostal('<<not-applicable>>')
				->setSite($toulouse)
			)
			->setSite($toulouse)
		)
		->setTrustedLocation(false)
		->setSite($toulouse)
	    ,
	];

	$newEvents = [];
	foreach($events as $event)
	{
	    $handler->handleEvent($newEvents, $event);
	}

	$this->assertEquals(0, count($newEvents));
    }

    public function testPlaces() {
	$comparator = $this->container->get('tbn.comparator');

	$toulouse   = (new Ville)->setNom('Toulouse')->setCodePostal('31500');
	$lyon	    = (new Ville)->setNom('Lyon')->setCodePostal('69003');
	
	$places = [
	    (new Place)->setNom('Galerie Artiempo')->setRue('33 Rue De La Colombette')->setVille($toulouse),
	    (new Place)->setNom('Pizz & Style')->setRue('21 bis rue de la Colombette')->setVille($toulouse),
	    (new Place)->setNom('Musee Saint-Raymond')->setRue('1 Ter Place Saint-Sernin')->setLatitude(43.6079604)->setLongitude(1.4410951)->setVille($toulouse),
	    (new Place)->setNom('L\'impro')->setRue('7 rue Gambetta')->setLatitude(43.602977746134)->setLongitude(1.4416940009976)->setVille($toulouse),
	];

	$testedItems = [
	    //Même nom mais pas même rue
	    ['expected' => 'Pizz & Style', 'item' => (new Place)->setNom('Chez Pizz and Style !')->setRue('Dumb street')->setVille($toulouse)],
	    //Même rue mais pas même nom
	    ['expected' => 'Pizz & Style', 'item' => (new Place)->setNom('Dumb name')->setRue('21 bis rue de la Colombette')->setVille($toulouse)],
	    //Même nom que A et même rue que B => B
	    ['expected' => 'Pizz & Style', 'item' => (new Place)->setNom('aux galeRies artiemPo')->setRue('21 bis rue de la Colombette')->setVille($toulouse)],
	    //Aucun nom connu + aucune rue connue
	    ['expected' => null, 'item' => (new Place)->setNom('Dumb name')->setRue('22 bis rue de la Colombette')->setVille($toulouse)],
	    //Même nom
	    ['expected' => null, 'item' => (new Place)->setNom('Toulouse')->setLatitude(43.6)->setLongitude(1.43333)],
	    //Même nom mais ville différente
	    ['expected' => null, 'item' => (new Place)->setNom('L\'improvidence')->setLatitude(45.757826691853)->setLongitude(4.8419701696829)->setRue('6 rue Chaponnay')->setVille($lyon)],
	];

	foreach($testedItems as $testedItem)
	{
	    $place = $comparator->getBestPlace($places, $testedItem['item']);
	    $this->assertEquals($testedItem['expected'], $place ? $place->getNom() : null);
	}
    }
}
