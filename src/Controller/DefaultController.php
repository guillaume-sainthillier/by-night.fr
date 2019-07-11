<?php

namespace App\Controller;

use App\Annotation\ReverseProxy;
use App\App\CityManager;
use App\Entity\Event;
use App\Form\Type\CityAutocompleteType;
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
     * @return Response
     */
    public function indexAction(Request $request, CityManager $cityManager)
    {
        $datas = [];
        if ($city = $cityManager->getCity()) {
            $datas = [
                'name' => $city->getFullName(),
                'city' => $city->getSlug(),
            ];
        }

        $stats = $this->getDoctrine()->getManager()->getRepository(Event::class)->getCountryEvents();
        $form = $this->createForm(CityAutocompleteType::class, $datas);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $datas = $form->getData();

            return $this->redirectToRoute('app_agenda_index', ['location' => $datas['city']]);
        }

        return $this->render('Default/index.html.twig', [
            'autocomplete_form' => $form->createView(),
            'stats' => $stats,
        ]);
    }
}
