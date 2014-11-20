<?php

namespace TBN\AgendaBundle\Controller;

use TBN\MainBundle\Controller\TBNController as Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use TBN\MainBundle\Entity\Site;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\UserBundle\Entity\User;
use TBN\AgendaBundle\Form\SearchType;
use TBN\AgendaBundle\Search\SearchAgenda;
use Symfony\Component\HttpFoundation\RedirectResponse;

class EventController extends Controller {

    /**
     *
     * @Cache(lastmodified="agenda.getDateModification()", etag="'Agenda' ~ agenda.getId() ~ agenda.getDateModification().format('Y-m-d H:i:s')")
     */
    public function detailsAction(Agenda $agenda) {
        $siteManager = $this->container->get("site_manager");
	$site = $siteManager->getCurrentSite();

        //Redirection vers le bon site
        if($agenda->getSite() !== $site)
        {
            return new RedirectResponse($this->get("router")->generate("tbn_agenda_details", [
                "slug" => $agenda->getSlug(),
                "subdomain"  => $agenda->getSite()->getSubdomain()
            ]));
        }
        
	$user = $this->get('security.context')->getToken()->getUser();


	$em = $this->getDoctrine()->getManager();
	$repo = $em->getRepository("TBNAgendaBundle:Calendrier");

	$participer = false;
	$interet = false;

	if ($user instanceof User) {
	    $calendrier = $repo->findOneBy(["user" => $user, "agenda" => $agenda]);
	    if ($calendrier !== null) {
		$participer = $calendrier->getParticipe();
		$interet = $calendrier->getInteret();
	    }
	}

	return $this->render('TBNAgendaBundle:Agenda:details.html.twig', [
		    "soiree" => $agenda,
		    "participer" => $participer,
		    "interet" => $interet
	]);
    }

    public function listAction(Request $request, $page) {
	if ($page <= 0) {
	    $page = 1;
	}

	$nbSoireeParPage = 15;
	$siteManager = $this->container->get("site_manager");
	$site = $siteManager->getCurrentSite();
	$em = $this->getDoctrine()->getManager();
	$repo = $em->getRepository("TBNAgendaBundle:Agenda"); // 100ms
	$search = new SearchAgenda();

	$communes = $this->getCommunes($repo, $site);
	$themes_manif = $this->getThemes($repo, $site);
	$types_manif = $this->getTypesManifestation($repo, $site);

        $action = $page > 1 ? $this->generateUrl("tbn_agenda_pagination", ["page" => $page]) : $this->generateUrl("tbn_agenda_index");
	$form = $this->createForm(new SearchType($types_manif, $communes, $themes_manif), $search, [
	    "action" => $action
	]); //100ms

	if ($request->getMethod() === "POST") {
	    $form->bind($request); //200ms
	}

	$soirees = $repo->findWithSearch($site, $search, $page, $nbSoireeParPage); //100ms
	$full_soirees = $repo->findCountWithSearch($site, $search); // 10ms

	$pageTotal = ceil($full_soirees / $nbSoireeParPage);

	return $this->render('TBNAgendaBundle:Agenda:soirees.html.twig', [
		    "soirees" => $soirees,
		    "page" => $page,
		    "pageTotal" => $pageTotal,
		    "search" => $search,
		    "form" => $form->createView() //20ms
	]); //800ms
    }

    protected function getTypesManifestation($repo, Site $site) {
	$cache = $this->get("winzou_cache");
	$key = 'types_manifesations.' . $site->getSubdomain();

	if (!$cache->contains($key)) {
	    $soirees_type_manifestation = $repo->getTypesManifestation($site);
	    $type_manifestation = [];

	    foreach ($soirees_type_manifestation as $soiree) {//
		$types_manifestation = preg_split("/,/", $soiree->getTypeManifestation());
		foreach ($types_manifestation as $type) {
		    $type = trim($type);
		    if (!in_array($type, $type_manifestation) and $type != "") {
			$type_manifestation[$type] = $type;
		    }
		}
	    }
	    ksort($type_manifestation);
	    $cache->save($key, $type_manifestation);
	}

	return $cache->fetch($key);
    }

    protected function getCommunes($repo, Site $site) {
	$cache = $this->get("winzou_cache");
	$key = 'communes.' . $site->getSubdomain();

	if (!$cache->contains($key)) {
	    $soirees_communes = $repo->getCommunes($site);
	    $communes = [];

	    foreach ($soirees_communes as $soiree) {//
		$full_communes = preg_split("/,/", $soiree->getCommune());
		foreach ($full_communes as $commune) {
		    $commune = trim($commune);
		    if (!in_array($commune, $communes) and $commune != "") {
			$communes[$commune] = $commune;
		    }
		}
	    }

	    ksort($communes);
	    $cache->save($key, $communes);
	}

	return $cache->fetch($key);
    }

    protected function getThemes($repo, Site $site) {
	$cache = $this->get("winzou_cache");
	$key = 'themes.' . $site->getSubdomain();

	if (!$cache->contains($key)) {
	    $soirees_themes = $repo->getThemes($site);
	    $themes = [];

	    foreach ($soirees_themes as $soiree) {//
		$full_themes = preg_split("/,/", $soiree->getThemeManifestation());
		foreach ($full_themes as $theme) {
		    $theme = trim($theme);
		    if (!in_array($theme, $themes) and $theme != "") {
			$themes[$theme] = $theme;
		    }
		}
	    }

	    ksort($themes);
	    $cache->save($key, $themes);
	}

	return $cache->fetch($key);
    }

}
