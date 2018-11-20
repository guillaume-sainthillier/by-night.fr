<?php

namespace App\Controller;

use App\Annotation\BrowserCache;
use App\App\CityManager;
use App\Form\Type\CityAutocompleteType;
use FOS\ElasticaBundle\Doctrine\RepositoryManager;
use Knp\Component\Pager\PaginatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

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
     *
     * @param PaginatorInterface $paginator
     * @param RepositoryManager  $repositoryManager
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(PaginatorInterface $paginator, RepositoryManager $repositoryManager)
    {
        /*
        $search = new SearchAgenda();

        $search->setTerm(AgendaRepository::CONCERT_TERMS);
        $concerts = $this->getResults($search, $paginator, $repositoryManager);

        $search->setTerm(AgendaRepository::SHOW_TERMS);
        $spectacles = $this->getResults($search, $paginator, $repositoryManager);

        $search->setTerm(AgendaRepository::STUDENT_TERMS);
        $etudiants = $this->getResults($search, $paginator, $repositoryManager);

        $search->setTerm(AgendaRepository::FAMILY_TERMS);
        $familles = $this->getResults($search, $paginator, $repositoryManager);
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
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
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
}
