<?php

namespace App\Controller;

use App\App\CityManager;
use App\Form\Type\CityAutocompleteType;
use App\Search\SearchAgenda;
use App\SearchRepository\AgendaRepository;
use FOS\ElasticaBundle\Doctrine\RepositoryManager;
use Knp\Component\Pager\PaginatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Annotation\BrowserCache;

class DefaultController extends Controller
{
    const EVENT_PER_CATEGORY = 7;

    /**
     * @var CityManager
     */
    private $cityManager;

    public function __construct(CityManager $cityManager)
    {
        $this->cityManager = $cityManager;
    }

    /**
     * @Route("/", name="tbn_main_index")
     * @Cache(expires="tomorrow", maxage="86400", smaxage="86400", public=true)
     * @BrowserCache(false)
     */
    public function indexAction()
    {
        /*
        $search = new SearchAgenda();

        $search->setTerm(AgendaRepository::CONCERT_TERMS);
        $concerts = $this->getResults($search);

        $search->setTerm(AgendaRepository::SHOW_TERMS);
        $spectacles = $this->getResults($search);

        $search->setTerm(AgendaRepository::STUDENT_TERMS);
        $etudiants = $this->getResults($search);

        $search->setTerm(AgendaRepository::FAMILY_TERMS);
        $familles = $this->getResults($search);
        */

        $datas = [];
        if ($city = $this->cityManager->getCity()) {
            $datas = [
                'name' => $city->getFullName(),
                'city' => $city->getSlug(),
            ];
        }
        $form = $this->createForm(CityAutocompleteType::class, $datas);

        return $this->render('Default/index.html.twig', [
//            'concerts'          => $concerts,
//            'spectacles'        => $spectacles,
//            'etudiants'         => $etudiants,
//            'familles'          => $familles,
            'autocomplete_form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/change-city", name="app_change_city")
     * @Method("POST")
     */
    public function changeCityAction(Request $request)
    {
        $form = $this->createForm(CityAutocompleteType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $datas = $form->getData();

            return $this->redirectToRoute('tbn_agenda_index', ['city' => $datas['city']]);
        }

        $this->addFlash('error', 'Veuillez seléctionner une ville');

        return $this->redirectToRoute('tbn_main_index');
    }

    private function getResults(SearchAgenda $search)
    {
        $paginator = $this->get(PaginatorInterface::class);
        /**
         * @var AgendaRepository
         */
        $repo    = $this->get(RepositoryManager::class)->getRepository('App:Agenda');
        $results = $repo->findWithSearch($search);

        return $paginator->paginate($results, 1, self::EVENT_PER_CATEGORY);
    }
}
