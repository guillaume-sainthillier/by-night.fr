<?php

namespace App\Controller;

use App\Annotation\ReverseProxy;
use App\App\CityManager;
use App\Entity\Agenda;
use App\Form\Type\CityAutocompleteType;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    const EVENT_PER_CATEGORY = 7;

    /**
     * @Route("/", name="app_main_index")
     * @ReverseProxy(expires="tomorrow")
     *
     * @param PaginatorInterface $paginator
     * @param RepositoryManagerInterface $repositoryManager
     *
     * @return Response
     */
    public function indexAction(CityManager $cityManager)
    {
        $datas = [];
        if ($city = $cityManager->getCity()) {
            $datas = [
                'name' => $city->getFullName(),
                'city' => $city->getSlug(),
            ];
        }

        $stats = $this->getDoctrine()->getManager()->getRepository(Agenda::class)->getCountryEvents();
        $form = $this->createForm(CityAutocompleteType::class, $datas);

        return $this->render('Default/index.html.twig', [
            'autocomplete_form' => $form->createView(),
            'stats' => $stats
        ]);
    }

    /**
     * @Route("/change-city", name="app_change_city", methods={"POST"})
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function changeCityAction(Request $request)
    {
        $form = $this->createForm(CityAutocompleteType::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $datas = $form->getData();

            return $this->redirectToRoute('app_agenda_index', ['location' => $datas['city']]);
        }

        $this->addFlash('error', 'Veuillez seléctionner une ville');

        return $this->redirectToRoute('app_main_index');
    }
}
