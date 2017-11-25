<?php

namespace AppBundle\Controller\Old\City;

use AppBundle\Controller\TBNController as Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AgendaController extends Controller
{
    /**
     * @Route("/{city}/agenda/sortir/{type}", name="tbn_agenda_sortir_old", requirements={"type": "concert|spectacle|etudiant|famille|exposition"})
     * @Route("/{city}/agenda/sortir/{type}/page/{page}", name="tbn_agenda_sortir_pagination_old", requirements={"type": "concert|spectacle|etudiant|famille|exposition", "page": "\d+"})
     * @Route("/{city}/agenda/sortir-a/{slug}", name="tbn_agenda_place_old", requirements={"slug": ".+"})
     * @Route("/{city}/agenda/sortir-a/{slug}/page/{page}", name="tbn_agenda_place_pagination_old", requirements={"slug": ".+", "page": "\d+"})
     * @Route("/{city}/agenda/tag/{tag}", name="tbn_agenda_tags_old", requirements={"type": "concert|spectacle|etudiant|famille|exposition"})
     * @Route("/{city}/agenda/tag/{tag}/page/{page}", name="tbn_agenda_tags_pagination_old", requirements={"type": "concert|spectacle|etudiant|famille|exposition", "page": "\d+"})
     */
    public function indexAction(Request $request)
    {
        $absoluteURL = $request->getUri();
        $absoluteURL = \str_replace('agenda/', '', $absoluteURL);

        return $this->redirect($absoluteURL, Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * @Route("/{city}/agenda/sortir-dans/{ville}", name="tbn_agenda_ville_old", requirements={"ville": ".*"})
     * @Route("/{city}/agenda/sortir-dans/{ville}/page/{page}", name="tbn_agenda_ville_pagination_old", requirements={"ville": ".*", "page": "\d+"})
     */
    public function cityAction($ville, $page = 1)
    {
        if ($page <= 0) {
            $page = 1;
        }

        if ($page > 1) {
            return $this->redirectToRoute('tbn_agenda_pagination', ['page' => $page, 'city' => $ville], Response::HTTP_MOVED_PERMANENTLY);
        } else {
            return $this->redirectToRoute('tbn_agenda_agenda', ['city' => $ville], Response::HTTP_MOVED_PERMANENTLY);
        }
    }

    /**
     * @Route("/{city}/sortir-dans/{ville}", name="tbn_agenda_ville", requirements={"ville": ".*"})
     * @Route("/{city}/sortir-dans/{ville}/page/{page}", name="tbn_agenda_ville_pagination", requirements={"ville": ".*", "page": "\d+"})
     */
    public function city2Action($ville, $page = 1)
    {
        if ($page <= 0) {
            $page = 1;
        }

        if ($page > 1) {
            return $this->redirectToRoute('tbn_agenda_pagination', ['page' => $page, 'city' => $ville], Response::HTTP_MOVED_PERMANENTLY);
        } else {
            return $this->redirectToRoute('tbn_agenda_agenda', ['city' => $ville], Response::HTTP_MOVED_PERMANENTLY);
        }
    }
}
