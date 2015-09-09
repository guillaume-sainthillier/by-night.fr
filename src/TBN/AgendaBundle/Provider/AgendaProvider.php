<?php

/**
 * RssAtomBundle
 *
 * @package RssAtomBundle/Provider
 *
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @copyright (c) 2013, Alexandre Debril
 *
 * creation date : 31 mars 2013
 *
 */

namespace TBN\AgendaBundle\Provider;

use \Symfony\Component\OptionsResolver\Options;
use Debril\RssAtomBundle\Protocol\Parser\FeedContent;
use Debril\RssAtomBundle\Protocol\Parser\Item;
use Debril\RssAtomBundle\Exception\FeedNotFoundException;
use Debril\RssAtomBundle\Provider\FeedContentProviderInterface;

use TBN\MainBundle\Site\SiteManager;
use Doctrine\ORM\EntityManager;
use BeSimple\I18nRoutingBundle\Routing\Router;

class AgendaProvider implements FeedContentProviderInterface
{

    /**

     *
     * @var EntityManager
     */
    protected $em;

    /**
     *
     * @var SiteManager
     */
    protected $site_manager;

    /**
     *
     * @var Router
     */
    protected $router;

    public function __construct(EntityManager $em, SiteManager $site_manager, Router $router)
    {
	$this->em = $em;
	$this->site_manager = $site_manager;
	$this->router = $router;
    }
    /**
     *
     * @param \Symfony\Component\OptionsResolver\Options $options
     * @return \Debril\RssAtomBundle\Protocol\Parser\FeedContent
     * @throws \Debril\RssAtomBundle\Protocol\FeedNotFoundException
     */
    public function getFeedContent(array $options)
    {
	$currentSite = $this->site_manager->getCurrentSite();

	$content = new FeedContent;

        $content->setTitle($currentSite->getNom()." By Night");
        $content->setDescription('Retrouvez tous les derniers événements à '.$currentSite->getNom());
        $content->setLink($this->router->generate("tbn_agenda_index", ["subdomain" => $currentSite->getSubdomain()], true));
        $content->setLastModified(new \DateTime);

	$repo = $this->em->getRepository("TBNAgendaBundle:Agenda");

	$agendas = $repo->findBy(["site" => $currentSite]);

	foreach($agendas as $agenda)
	{
	    $item = new Item;

	    $item->setPublicId($agenda->getSlug());
	    $item->setLink($this->router->generate("tbn_agenda_details", ["subdomain" => $currentSite->getSubdomain(), "slug" => $agenda->getSlug()], true));
	    $item->setTitle($agenda->getNom());
	    $item->setDescription($agenda->getDescriptif());
	    $item->setUpdated($agenda->getDateModification());
	    $item->setAuthor($agenda->getUser() ? $agenda->getUser()->getUsername() : null);

	    $content->addItem($item);
	}


        return $content;
    }

}

