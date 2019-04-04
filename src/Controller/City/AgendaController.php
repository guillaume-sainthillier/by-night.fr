<?php

namespace App\Controller\City;

use App\Annotation\BrowserCache;
use App\Controller\TBNController as BaseController;
use App\Entity\Agenda;
use App\Entity\City;
use App\Entity\Place;
use App\Form\Type\SearchType;
use App\Repository\AgendaRepository;
use App\Search\SearchAgenda;
use Doctrine\Common\Cache\Cache as DoctrineCache;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AgendaController extends BaseController
{
    const EVENT_PER_PAGE = 15;

    protected function handleSearch(SearchAgenda $search, City $city, $type, $tag, $ville, Place $place = null)
    {
        $term = null;
        if (null !== $ville) {
            $term = null;
            $search->setCommune([$ville]);
            $formAction = $this->generateUrl('app_agenda_ville', ['ville' => $ville, 'city' => $city->getSlug()]);
        } elseif (null !== $place) {
            $term = null;
            $search->setLieux([$place->getId()]);
            $formAction = $this->generateUrl('app_agenda_place', ['slug' => $place->getSlug(), 'city' => $city->getSlug()]);
        } elseif (null !== $tag) {
            $term = null;
            $search->setTag($tag);
            $formAction = $this->generateUrl('app_agenda_tags', ['tag' => $tag, 'city' => $city->getSlug()]);
        } elseif (null !== $type) {
            $formAction = $this->generateUrl('app_agenda_sortir', ['type' => $type, 'city' => $city->getSlug()]);
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
            $formAction = $this->generateUrl('app_agenda_agenda', ['city' => $city->getSlug()]);
        }

        $search->setTerm($term);

        return $formAction;
    }

    /**
     * @Cache(expires="+30 minutes", smaxage="1800")
     * @Route("/agenda/{page}", name="app_agenda_agenda", requirements={"page": "\d+"})
     * @Route("/agenda/sortir/{type}/{page}", name="app_agenda_sortir", requirements={"type": "concert|spectacle|etudiant|famille|exposition", "page": "\d+"})
     * @Route("/agenda/sortir-a/{slug}/{page}", name="app_agenda_place", requirements={"page": "\d+"})
     * @Route("/agenda/tag/{tag}/{page}", name="app_agenda_tags", requirements={"page": "\d+"})
     * @BrowserCache(false)
     *
     * @param Request $request
     * @param City $city
     * @param int $page
     * @param null $type
     * @param null $tag
     * @param null $ville
     * @param null $slug
     * @param RepositoryManagerInterface $repositoryManager
     *
     * @return Response
     */
    public function indexAction(Request $request, City $city, DoctrineCache $memoryCache, PaginatorInterface $paginator, RepositoryManagerInterface $repositoryManager, $page = 1, $type = null, $tag = null, $ville = null, $slug = null)
    {
        //État de la page
        $isAjax = $request->isXmlHttpRequest();
        $isPost = $request->isMethod('POST');
        $isUserPostSearch = $isPost && !$isAjax;

        $routeParams = [
            'page' => $page + 1,
            'city' => $city->getSlug(),
        ];

        if (null !== $type) {
            $routeParams['type'] = $type;
        } elseif (null !== $tag) {
            $routeParams['tag'] = $tag;
        } elseif (null !== $slug) {
            $routeParams['slug'] = $slug;
        } elseif (null !== $ville) {
            $routeParams['ville'] = $ville;
        }

        $paginateURL = $this->generateUrl($request->attributes->get('_route'), $routeParams);

        //Récupération du repo des événements
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository(Agenda::class);

        //Recherche des événements
        $search = new SearchAgenda();
        $search->setCity($city);
        $place = null;

        if (null !== $slug) {
            $place = $em->getRepository(Place::class)->findOneBy(['slug' => $slug]);
            if (!$place) {
                return $this->redirectToRoute('app_agenda_agenda', ['city' => $city->getSlug()]);
            }

            if ($place->getCity()->getId() !== $city->getId()) {
                return $this->redirectToRoute('app_agenda_place', ['city' => $place->getCity()->getSlug(), 'slug' => $slug]);
            }
        }
        $formAction = $this->handleSearch($search, $city, $type, $tag, $ville, $place);

        //Récupération des lieux, types événements et villes
        $lieux = $this->getPlaces($memoryCache, $repo, $city);
        $types_manif = $this->getTypesEvenements($memoryCache, $repo, $city);
        $communes = $this->getVilles($memoryCache, $repo, $city);

        //Création du formulaire
        $form = $this->createForm(SearchType::class, $search, [
            'action' => $formAction,
            'lieux' => $lieux,
            'types_manif' => $types_manif,
            'communes' => $communes,
        ]);

        //Bind du formulaire avec la requête courante
        $form->handleRequest($request);
        if ($isUserPostSearch) {
            $page = 1;
        } elseif ($isPost) {
            $page = $search->getPage();
        }

        //Recherche ElasticSearch
        $repository = $repositoryManager->getRepository(Agenda::class);
        $results = $repository->findWithSearch($search);

        $pagination = $paginator->paginate($results, $page, self::EVENT_PER_PAGE);
        $nbSoireesTotales = $pagination->getTotalItemCount();
        $soirees = $pagination;

        return $this->render('City/Agenda/index.html.twig', [
            'city' => $city,
            'villeName' => $ville,
            'placeName' => (null !== $place) ? $place->getNom() : null,
            'placeSlug' => (null !== $place) ? $place->getSlug() : null,
            'place' => $place,
            'tag' => $tag,
            'type' => $type,
            'soirees' => $soirees,
            'nbEvents' => $nbSoireesTotales,
            'maxPerEvent' => self::EVENT_PER_PAGE,
            'page' => $page,
            'search' => $search,
            'isPost' => $isPost,
            'isAjax' => $isAjax,
            'paginateURL' => $paginateURL,
            'form' => $form->createView(),
        ]);
    }

    protected function getTypesEvenements(DoctrineCache $cache, AgendaRepository $repo, City $city)
    {
        $key = 'categories_evenements.' . $city->getSlug();

        if (!$cache->contains($key)) {
            $soirees_type_manifestation = $repo->getTypesEvenements($city);
            $type_manifestation = [];

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

    protected function getVilles(DoctrineCache $cache, AgendaRepository $repo, City $city)
    {
        $key = 'villes.' . $city->getSlug();

        if (!$cache->contains($key)) {
            $places = $repo->getAgendaVilles($city);
            $tab_villes = [];
            foreach ($places as $place) {
                $tab_villes[$place->getCity()->getName()] = $place->getCity()->getName();
            }

            $cache->save($key, $tab_villes, 24 * 60 * 60);
        }

        return $cache->fetch($key);
    }

    protected function getPlaces(DoctrineCache $cache, AgendaRepository $repo, City $city)
    {
        $key = 'places.' . $city->getSlug();

        if (!$cache->contains($key)) {
            $places = $repo->getAgendaPlaces($city);
            $lieux = array();
            foreach ($places as $place) {
                $lieux[$place->getNom()] = $place->getId();
            }

            $cache->save($key, $lieux, 24 * 60 * 60);
        }

        return $cache->fetch($key);
    }
}
