<?php

namespace TBN\AgendaBundle\Controller;

use TBN\MainBundle\Controller\TBNController as Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use TBN\MainBundle\Entity\Site;
use TBN\AgendaBundle\Entity\Place;
use TBN\AgendaBundle\Entity\Ville;
use TBN\AgendaBundle\Entity\Agenda;

use TBN\UserBundle\Entity\User;
use TBN\AgendaBundle\Form\Type\SearchType;
use TBN\AgendaBundle\Search\SearchAgenda;
use Symfony\Component\HttpFoundation\RedirectResponse;

class EventController extends Controller {

    /**
     * @Cache(lastmodified="agenda.getDateModification()", etag="'Agenda' ~ agenda.getId() ~ agenda.getDateModification().format('Y-m-d H:i:s')")
     */
    public function detailsAction(Agenda $agenda) {
        $siteManager = $this->container->get('site_manager');
	$site = $siteManager->getCurrentSite();

        //Redirection vers le bon site
        if($agenda->getSite() !== $site)
        {
            return new RedirectResponse($this->get('router')->generate('tbn_agenda_details', [
                'slug' => $agenda->getSlug(),
                'subdomain'  => $agenda->getSite()->getSubdomain()
            ]));
        }

	$user = $this->get('security.token_storage')->getToken()->getUser();


	$em = $this->getDoctrine()->getManager();
	$repo = $em->getRepository('TBNAgendaBundle:Calendrier');

	$participer = false;
	$interet = false;

	if ($user instanceof User) {
	    $calendrier = $repo->findOneBy(['user' => $user, 'agenda' => $agenda]);
	    if ($calendrier !== null) {
		$participer = $calendrier->getParticipe();
		$interet = $calendrier->getInteret();
	    }
	}

	return $this->render('TBNAgendaBundle:Agenda:details.html.twig', [
		    'soiree' => $agenda,
		    'participer' => $participer,
		    'interet' => $interet
	]);
    }

    public function listAction(Request $request, $page) {
	//État de la page
	$isAjax		    = $request->isXmlHttpRequest();
	$isPost		    = $request->isMethod('POST');
	$isUserPostSearch   = $isPost && !$isAjax;

        //Pagination
        $nbSoireeParPage = 15;
	
        //Gestion du site courant
	$siteManager	= $this->container->get('site_manager');
	$site		= $siteManager->getCurrentSite();

        //Récupération du repo des événéments
	$em	= $this->getDoctrine()->getManager();
	$repo	= $em->getRepository('TBNAgendaBundle:Agenda');

        //Recherche des événements
	$search = new SearchAgenda();

	//Récupération des lieux, types événéments et villes
	$lieux		= $this->getPlaces($repo, $site);
	$types_manif	= $this->getTypesEvenements($repo, $site);
	$communes	= $this->getVilles($repo, $site);

	//Création du formulaire
	$form		= $this->createForm(new SearchType($types_manif, $lieux, $communes), $search, [
	    'action' => $this->generateUrl('tbn_agenda_index')
	]);

	//Bind du formulaire avec la requête courante
	$form->handleRequest($request);
	if($isUserPostSearch)
	{
	    $page = 1;
	}elseif($isPost)
	{
	    $page = $search->getPage();
	}
	
        //Recherche ElasticSearch
        $repositoryManager = $this->get('fos_elastica.manager');
        $repository = $repositoryManager->getRepository('TBNAgendaBundle:Agenda');
	$results = $repository->findWithSearch($site, $search); //100ms
	$results->setCurrentPage($page)->setMaxPerPage($nbSoireeParPage);
	$soirees = $results->getCurrentPageResults();
	$nbSoireesTotales = $results->getNbResults();
        
	return $this->render('TBNAgendaBundle:Agenda:soirees.html.twig', [
		    'soirees' => $soirees,
		    'nbEvents' => $nbSoireesTotales,
		    'maxPerEvent' => $nbSoireeParPage,
		    'page' => $page,
		    'search' => $search,
		    'isPost' => $isPost,
		    'form' => $form->createView()
	]); 
    }

    protected function getTypesEvenements($repo, Site $site) {
	$cache = $this->get('winzou_cache');
	$key = 'types_evenements.' . $site->getSubdomain();

	if (!$cache->contains($key)) {
	    $soirees_type_manifestation = $repo->getTypesEvenements($site);
	    $type_manifestation = [];

	    foreach ($soirees_type_manifestation as $soiree) {//
		$types_manifestation = preg_split('/,/', $soiree->getTypeManifestation());
		foreach ($types_manifestation as $type) {
		    $type = trim($type);
		    if (!in_array($type, $type_manifestation) && $type != '') {
			$type_manifestation[$type] = $type;
		    }
		}
	    }
	    ksort($type_manifestation);
	    $cache->save($key, $type_manifestation, 24*60*60);
	}

	return $cache->fetch($key);
    }

    protected function getVilles($repo, Site $site) {
	$cache = $this->get('winzou_cache');
	$key = 'villes.' . $site->getSubdomain();

	if (!$cache->contains($key)) {
	    $villes = $repo->getAgendaVilles($site);
	    $tab_villes = [];
	    foreach($villes as $ville)
	    {
		$tab_villes[$ville->getId()] = $ville->getNom();
	    }

	    $cache->save($key, $tab_villes, 24*60*60);
	}

	return $cache->fetch($key);
    }

    protected function getPlaces($repo, Site $site) {
	$cache = $this->get('winzou_cache');
	$key = 'places.' . $site->getSubdomain();

	if (!$cache->contains($key)) {
	    $places = $repo->getAgendaPlaces($site);
	    $lieux  = array();
	    foreach($places as $place)
	    {
		$lieux[$place->getId()] = $place->getNom();
	    }

	    $cache->save($key, $lieux, 24*60*60);
	}

	return $cache->fetch($key);
    }

}
