<?php

namespace TBN\AgendaBundle\Controller;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use TBN\CommentBundle\Entity\Comment;
use TBN\CommentBundle\Form\Type\CommentType;
use TBN\MainBundle\Controller\TBNController as Controller;
use SocialLinks\Page;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use FOS\HttpCacheBundle\Configuration\Tag;
use TBN\MainBundle\Entity\Site;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\AgendaBundle\Entity\Place;
use TBN\AgendaBundle\Entity\Calendrier;

use TBN\AgendaBundle\Form\Type\SearchType;
use TBN\AgendaBundle\Search\SearchAgenda;
use Symfony\Component\HttpFoundation\RedirectResponse;
use TBN\MainBundle\Invalidator\EventInvalidator;

class EventController extends Controller
{
    protected function getCreateCommentForm(Comment $comment, Agenda $soiree)
    {
        return $this->createForm(CommentType::class, $comment, [
            'action' => $this->generateUrl('tbn_comment_new', ["id" => $soiree->getId()]),
            'method' => 'POST'
        ])
            ->add("poster", SubmitType::class, [
                "label" => "Poster",
                "attr" => [
                    "class" => "btn btn-primary btn-submit btn-raised",
                    "data-loading-text" => "En cours..."
                ]
            ]);
    }

    /**
     * @Tag("detail-event")
     */
    public function detailsAction($slug, $id = null)
    {
        $result = $this->checkEventUrl($slug, $id);
        if($result instanceof Response) {
            return $result;
        }
        $agenda = $result;

        $siteManager = $this->container->get('site_manager');
        $site = $siteManager->getCurrentSite();

        //Redirection vers le bon site
        if ($agenda->getSite() !== $site) {
            return new RedirectResponse($this->get('router')->generate('tbn_agenda_details', [
                'slug' => $agenda->getSlug(),
                'id' => $agenda->getId(),
                'subdomain' => $agenda->getSite()->getSubdomain()
            ]));
        }

        $comment = new Comment();
        $form = $this->getCreateCommentForm($comment, $agenda);
        $nbComments = $agenda->getCommentaires()->count();

        $response = $this->render('TBNAgendaBundle:Agenda:details.html.twig', [
            'soiree' => $agenda,
            'form' => $form->createView(),
            'nb_comments' => $nbComments,
            'stats' => $this->getAgendaStats($agenda)
        ]);

        $response->headers->add([
            'X-No-Browser-Cache' => '1'
        ]);

        $now = new \DateTime();
        if($agenda->getDateFin() < $now) {
            $expires = $now;
            $expires->modify("+1 year");
            $ttl = 31536000;
        }else {
            list($expires, $ttl) = $this->getSecondsUntil(168);
        }

        $response
            ->setSharedMaxAge($ttl)
            ->setExpires($expires);

        $this->get('fos_http_cache.handler.tag_handler')->addTags([
           EventInvalidator::getEventDetailTag($agenda)
        ]);

        return $response;
    }

    /**
     * @param Agenda $agenda
     * @Cache(expires="+12 hours", smaxage="43200")
     * @return Response
     */
    public function shareAction(Agenda $agenda) {
        $link = $this->generateUrl('tbn_agenda_details', [
            'slug' => $agenda->getSlug(),
            'id' => $agenda->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $eventProfile = $this->get('tbn.profile_picture.event')->getOriginalPictureUrl($agenda);

        $page = new Page([
            'url' => $link,
            'title' => $agenda->getNom(),
            'text' => $agenda->getDescriptif(),
            'image' => $eventProfile,
        ]);

        $page->shareCount(['twitter', 'facebook', 'plus']);

        return $this->render("@TBNAgenda/Hinclude/shares.html.twig", [
            "shares" => [
                'facebook' => $page->facebook,
                'twitter' => $page->twitter,
                'google-plus' => $page->plus
            ]
        ]);
    }

    protected function getAgendaStats(Agenda $agenda)
    {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("TBNAgendaBundle:Agenda");

        $participer = false;
        $interet = false;

        $user = $this->getUser();
        if ($user) {
            $repoCalendrier = $em->getRepository('TBNAgendaBundle:Calendrier');
            $calendrier = $repoCalendrier->findOneBy(['user' => $user, 'agenda' => $agenda]);
            if ($calendrier !== null) {
                $participer = $calendrier->getParticipe();
                $interet = $calendrier->getInteret();
            }

            if ($agenda->getFacebookEventId() && $user->getInfo() && $user->getInfo()->getFacebookId()) {
                $cache = $this->get('memory_cache');
                $key = 'users.' . $user->getId() . '.stats.' . $agenda->getId();
                if (!$cache->contains($key)) {
                    $api = $this->get('tbn.social.facebook_admin');
                    $stats = $api->getUserEventStats($agenda->getFacebookEventId(), $user->getInfo()->getFacebookId());
                    $cache->save($key, $stats);
                }
                $stats = $cache->fetch($key);

                if ($stats['participer'] || $stats['interet']) {
                    if (null === $calendrier) {
                        $calendrier = new Calendrier;
                        $calendrier->setUser($user)->setAgenda($agenda);
                    }

                    $participer = $calendrier->getParticipe() || $stats['participer'];
                    $interet = $calendrier->getInteret() || $stats['interet'];

                    $calendrier
                        ->setParticipe($participer)
                        ->setInteret($interet);

                    $em->persist($calendrier);
                    $em->flush();
                }
            }
        }

        return [
            "tendancesParticipations" => $repo->findAllTendancesParticipations($agenda),
            "tendancesInterets" => $repo->findAllTendancesInterets($agenda),
            "count_participer" => $agenda->getParticipations() + $agenda->getFbParticipations(),
            "count_interets" => $agenda->getInterets() + $agenda->getFbInterets(),
            'participer' => $participer,
            'interet' => $interet
        ];
    }

    protected function handleSearch(SearchAgenda $search, $type, $tag, $ville, Place $place = null)
    {
        $term = null;
        if ($ville !== null) {
            $term = null;
            $search->setCommune([$ville]);
            $formAction = $this->generateUrl('tbn_agenda_ville', ['ville' => $ville]);
        } elseif ($place !== null) {
            $term = null;
            $search->setLieux([$place->getId()]);
            $formAction = $this->generateUrl('tbn_agenda_place', ['slug' => $place->getSlug()]);
        } elseif ($tag !== null) {
            $term = null;
            $search->setTag($tag);
            $formAction = $this->generateUrl('tbn_agenda_tags', ['tag' => $tag]);
        } elseif ($type !== null) {
            $formAction = $this->generateUrl('tbn_agenda_sortir', ['type' => $type]);
            switch ($type) {
                case 'exposition':
                    $term = 'expo, exposition';
                    break;
                case 'concert':
                    $term = 'concert, musique, artiste';
                    break;
                case 'famille':
                    $term = 'famille, enfant';
                    break;
                case 'spectacle':
                    $term = 'spectacle, exposition, théâtre';
                    break;
                case 'etudiant':
                    $term = 'soirée, étudiant, bar, discothèque, boîte de nuit, after';
                    break;
            }
        } else {
            $formAction = $this->generateUrl('tbn_agenda_agenda');
        }

        $search->setTerm($term);

        return $formAction;
    }

    /**
     * @Cache(expires="+2 hours", smaxage="7200")
     */
    public function indexAction()
    {
        $siteManager = $this->get('site_manager');
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("TBNAgendaBundle:Agenda");

        $site = $siteManager->getCurrentSite();
        $search = (new SearchAgenda)->setDu(null);
        $topEvents = $repo->findTopSoiree($site, 1, 7);

        $response = $this->render('TBNAgendaBundle:Agenda:index.html.twig', [
            'topEvents' => $topEvents,
            'nbEvents' => $repo->findCountWithSearch($site, $search)
        ]);

        $response->headers->add([
            'X-No-Browser-Cache' => '1'
        ]);

        return $response;
    }

    /**
     * @Cache(expires="+30 minutes", smaxage="1800")
     */
    public function listAction(Request $request, $page, $type, $tag, $ville, $slug, $paginateRoute = 'tbn_agenda_pagination')
    {
        //État de la page
        $isAjax = $request->isXmlHttpRequest();
        $isPost = $request->isMethod('POST');
        $isUserPostSearch = $isPost && !$isAjax;

        $routeParams = ['page' => $page + 1];
        if ($paginateRoute === 'tbn_agenda_sortir_pagination') {
            $routeParams['type'] = $type;
        } elseif ($paginateRoute === 'tbn_agenda_tags_pagination') {
            $routeParams['tag'] = $tag;
        } elseif ($paginateRoute === 'tbn_agenda_place_pagination') {
            $routeParams['slug'] = $slug;
        } elseif ($paginateRoute === 'tbn_agenda_ville_pagination') {
            $routeParams['ville'] = $ville;
        }
        $paginateURL = $this->generateUrl($paginateRoute, $routeParams);

        //Pagination
        $nbSoireeParPage = 15;

        //Gestion du site courant
        $siteManager = $this->container->get('site_manager');
        $site = $siteManager->getCurrentSite();

        //Récupération du repo des événéments
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository('TBNAgendaBundle:Agenda');

        //Recherche des événements
        $search = new SearchAgenda();
        $place = null;
        if ($slug !== null) {
            $place = $em->getRepository('TBNAgendaBundle:Place')->findOneBy(['slug' => $slug]);
            if(! $place) {
                return new RedirectResponse($this->generateUrl('tbn_agenda_agenda'));
            }
        }
        $formAction = $this->handleSearch($search, $type, $tag, $ville, $place);

        //Récupération des lieux, types événéments et villes
        $lieux = $this->getPlaces($repo, $site);
        $types_manif = $this->getTypesEvenements($repo, $site);
        $communes = $this->getVilles($repo, $site);

        //Création du formulaire
        $form = $this->createForm(SearchType::class, $search, [
            'action' => $formAction,
            'lieux' => $lieux,
            'types_manif' => $types_manif,
            'communes' => $communes
        ]);

        //Bind du formulaire avec la requête courante
        $form->handleRequest($request);
        if ($isUserPostSearch) {
            $page = 1;
        } elseif ($isPost) {
            $page = $search->getPage();
        }

        //Recherche ElasticSearch
        $repositoryManager = $this->get('fos_elastica.manager');
        $repository = $repositoryManager->getRepository('TBNAgendaBundle:Agenda');
        $results = $repository->findWithSearch($site, $search); //100ms

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate($results, $page, $nbSoireeParPage);
        $nbSoireesTotales = $pagination->getTotalItemCount();
        $soirees = $pagination;

        $response = $this->render('TBNAgendaBundle:Agenda:soirees.html.twig', [
            'villeName' => $ville,
            'placeName' => (null !== $place) ? $place->getNom() : null,
            'placeSlug' => (null !== $place) ? $place->getSlug() : null,
            'tag' => $tag,
            'type' => $type,
            'soirees' => $soirees,
            'nbEvents' => $nbSoireesTotales,
            'maxPerEvent' => $nbSoireeParPage,
            'page' => $page,
            'search' => $search,
            'isPost' => $isPost,
            'isAjax' => $isAjax,
            'paginateURL' => $paginateURL,
            'form' => $form->createView()
        ]);

        $response->headers->add([
            'X-No-Browser-Cache' => '1'
        ]);

        return $response;
    }

    protected function getTypesEvenements($repo, Site $site)
    {
        $cache = $this->get('memory_cache');
        $key = 'categories_evenements.' . $site->getSubdomain();

        if (!$cache->contains($key)) {
            $soirees_type_manifestation = $repo->getTypesEvenements($site);
            $type_manifestation = [];

            foreach ($soirees_type_manifestation as $soiree) {//
                $types_manifestation = preg_split('/,/', $soiree->getCategorieManifestation());
                foreach ($types_manifestation as $type) {
                    $type = array_map('trim', explode('//', $type))[0];
                    if (!in_array($type, $type_manifestation) && $type != '') {
                        $type_manifestation[$type] = $type;
                    }
                }
            }
            ksort($type_manifestation);
            $cache->save($key, $type_manifestation, 24 * 60 * 60);
        }

        return $cache->fetch($key);
    }

    protected function getVilles($repo, Site $site)
    {
        $cache = $this->get('memory_cache');
        $key = 'villes.' . $site->getSubdomain();

        if (!$cache->contains($key)) {
            $places = $repo->getAgendaVilles($site);
            $tab_villes = [];
            foreach ($places as $place) {
                $tab_villes[$place->getVille()] = $place->getVille();
            }

            $cache->save($key, $tab_villes, 24 * 60 * 60);
        }

        return $cache->fetch($key);
    }

    protected function getPlaces($repo, Site $site)
    {
        $cache = $this->get('memory_cache');
        $key = 'places.' . $site->getSubdomain();

        if (!$cache->contains($key)) {
            $places = $repo->getAgendaPlaces($site);
            $lieux = array();
            foreach ($places as $place) {
                $lieux[$place->getNom()] = $place->getId();
            }

            $cache->save($key, $lieux, 24 * 60 * 60);
        }

        return $cache->fetch($key);
    }

}
