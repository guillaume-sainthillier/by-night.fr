<?php

namespace App\Controller\City;

use App\Entity\Agenda;
use App\Repository\AgendaRepository;
use App\Controller\TBNController as Controller;
use FOS\ElasticaBundle\Doctrine\RepositoryManager;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Component\Routing\Annotation\Route;
use App\Annotation\BrowserCache;
use App\Entity\City;
use App\Entity\Place;
use App\Form\Type\SearchType;
use App\Search\SearchAgenda;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AgendaController extends Controller
{
    const EVENT_PER_PAGE = 15;

    protected function handleSearch(SearchAgenda $search, City $city, $type, $tag, $ville, Place $place = null)
    {
        $term = null;
        if (null !== $ville) {
            $term = null;
            $search->setCommune([$ville]);
            $formAction = $this->generateUrl('tbn_agenda_ville', ['ville' => $ville, 'city' => $city->getSlug()]);
        } elseif (null !== $place) {
            $term = null;
            $search->setLieux([$place->getId()]);
            $formAction = $this->generateUrl('tbn_agenda_place', ['slug' => $place->getSlug(), 'city' => $city->getSlug()]);
        } elseif (null !== $tag) {
            $term = null;
            $search->setTag($tag);
            $formAction = $this->generateUrl('tbn_agenda_tags', ['tag' => $tag, 'city' => $city->getSlug()]);
        } elseif (null !== $type) {
            $formAction = $this->generateUrl('tbn_agenda_sortir', ['type' => $type, 'city' => $city->getSlug()]);
            switch ($type) {
                case 'exposition':
                    $term = \App\SearchRepository\AgendaRepository::EXPO_TERMS;

                    break;
                case 'concert':
                    $term = \App\SearchRepository\AgendaRepository::CONCERT_TERMS;

                    break;
                case 'famille':
                    $term = \App\SearchRepository\AgendaRepository::FAMILY_TERMS;

                    break;
                case 'spectacle':
                    $term = \App\SearchRepository\AgendaRepository::SHOW_TERMS;

                    break;
                case 'etudiant':
                    $term = \App\SearchRepository\AgendaRepository::STUDENT_TERMS;

                    break;
            }
        } else {
            $formAction = $this->generateUrl('tbn_agenda_agenda', ['city' => $city->getSlug()]);
        }

        $search->setTerm($term);

        return $formAction;
    }

    /**
     * @Cache(expires="+30 minutes", smaxage="1800")
     * @Route("/agenda", name="tbn_agenda_agenda")
     * @Route("/agenda/page/{page}", name="tbn_agenda_pagination", requirements={"page": "\d+"})
     * @Route("/sortir/{type}", name="tbn_agenda_sortir", requirements={"type": "concert|spectacle|etudiant|famille|exposition"})
     * @Route("/sortir/{type}/page/{page}", name="tbn_agenda_sortir_pagination", requirements={"type": "concert|spectacle|etudiant|famille|exposition", "page": "\d+"})
     * @Route("/sortir-a/{slug}", name="tbn_agenda_place", requirements={"slug": ".+"})
     * @Route("/sortir-a/{slug}/page/{page}", name="tbn_agenda_place_pagination", requirements={"slug": ".+", "page": "\d+"})
     * @Route("/tag/{tag}", name="tbn_agenda_tags", requirements={"type": "concert|spectacle|etudiant|famille|exposition"})
     * @Route("/tag/{tag}/page/{page}", name="tbn_agenda_tags_pagination", requirements={"type": "concert|spectacle|etudiant|famille|exposition", "page": "\d+"})
     * @BrowserCache(false)
     */
    public function indexAction(Request $request, City $city, $page = 1, $type = null, $tag = null, $ville = null, $slug = null, $paginateRoute = 'tbn_agenda_pagination')
    {
        //État de la page
        $isAjax           = $request->isXmlHttpRequest();
        $isPost           = $request->isMethod('POST');
        $isUserPostSearch = $isPost && !$isAjax;

        $routeParams = [
            'page' => $page + 1,
            'city' => $city->getSlug(),
        ];
        if ('tbn_agenda_sortir_pagination' === $paginateRoute) {
            $routeParams['type'] = $type;
        } elseif ('tbn_agenda_tags_pagination' === $paginateRoute) {
            $routeParams['tag'] = $tag;
        } elseif ('tbn_agenda_place_pagination' === $paginateRoute) {
            $routeParams['slug'] = $slug;
        } elseif ('tbn_agenda_ville_pagination' === $paginateRoute) {
            $routeParams['ville'] = $ville;
        }
        $paginateURL = $this->generateUrl($paginateRoute, $routeParams);

        //Récupération du repo des événéments
        $em   = $this->getDoctrine()->getManager();
        $repo = $em->getRepository(Agenda::class);

        //Recherche des événements
        $search = new SearchAgenda();
        $search->setCity($city);
        $place = null;
        if (null !== $slug) {
            $place = $em->getRepository(Place::class)->findOneBy(['slug' => $slug]);
            if (!$place) {
                return new RedirectResponse($this->generateUrl('tbn_agenda_agenda', ['city' => $city->getSlug()]));
            }
        }
        $formAction = $this->handleSearch($search, $city, $type, $tag, $ville, $place);

        //Récupération des lieux, types événéments et villes
        $lieux       = $this->getPlaces($repo, $city);
        $types_manif = $this->getTypesEvenements($repo, $city);
        $communes    = $this->getVilles($repo, $city);

        //Création du formulaire
        $form = $this->createForm(SearchType::class, $search, [
            'action'      => $formAction,
            'lieux'       => $lieux,
            'types_manif' => $types_manif,
            'communes'    => $communes,
        ]);

        //Bind du formulaire avec la requête courante
        $form->handleRequest($request);
        if ($isUserPostSearch) {
            $page = 1;
        } elseif ($isPost) {
            $page = $search->getPage();
        }

        //Recherche ElasticSearch
        $repositoryManager = $this->get(RepositoryManager::class);
        $repository        = $repositoryManager->getRepository(Agenda::class);
        $results           = $repository->findWithSearch($search);

        $paginator        = $this->get(PaginatorInterface::class);
        $pagination       = $paginator->paginate($results, $page, self::EVENT_PER_PAGE);
        $nbSoireesTotales = $pagination->getTotalItemCount();
        $soirees          = $pagination;

        return $this->render('City/Agenda/index.html.twig', [
            'city'        => $city,
            'villeName'   => $ville,
            'placeName'   => (null !== $place) ? $place->getNom() : null,
            'placeSlug'   => (null !== $place) ? $place->getSlug() : null,
            'place'       => $place,
            'tag'         => $tag,
            'type'        => $type,
            'soirees'     => $soirees,
            'nbEvents'    => $nbSoireesTotales,
            'maxPerEvent' => self::EVENT_PER_PAGE,
            'page'        => $page,
            'search'      => $search,
            'isPost'      => $isPost,
            'isAjax'      => $isAjax,
            'paginateURL' => $paginateURL,
            'form'        => $form->createView(),
        ]);
    }

    protected function getTypesEvenements(AgendaRepository $repo, City $city)
    {
        $cache = $this->get('memory_cache');
        $key   = 'categories_evenements.' . $city->getSlug();

        if (!$cache->contains($key)) {
            $soirees_type_manifestation = $repo->getTypesEvenements($city);
            $type_manifestation         = [];

            foreach ($soirees_type_manifestation as $soiree_type_manifestation) {//
                $types_manifestation = \preg_split('/,/', $soiree_type_manifestation);
                foreach ($types_manifestation as $type) {
                    $type = \array_map('trim', \explode('//', $type))[0];
                    if (!\in_array($type, $type_manifestation) && '' != $type) {
                        $type_manifestation[$type] = $type;
                    }
                }
            }
            \ksort($type_manifestation);
            $cache->save($key, $type_manifestation, 24 * 60 * 60);
        }

        return $cache->fetch($key);
    }

    protected function getVilles(AgendaRepository $repo, City $city)
    {
        $cache = $this->get('memory_cache');
        $key   = 'villes.' . $city->getSlug();

        if (!$cache->contains($key)) {
            $places     = $repo->getAgendaVilles($city);
            $tab_villes = [];
            foreach ($places as $place) {
                $tab_villes[$place->getCity()->getName()] = $place->getCity()->getName();
            }

            $cache->save($key, $tab_villes, 24 * 60 * 60);
        }

        return $cache->fetch($key);
    }

    protected function getPlaces(AgendaRepository $repo, City $city)
    {
        $cache = $this->get('memory_cache');
        $key   = 'places.' . $city->getSlug();

        if (!$cache->contains($key)) {
            $places = $repo->getAgendaPlaces($city);
            $lieux  = array();
            foreach ($places as $place) {
                $lieux[$place->getNom()] = $place->getId();
            }

            $cache->save($key, $lieux, 24 * 60 * 60);
        }

        return $cache->fetch($key);
    }
}
