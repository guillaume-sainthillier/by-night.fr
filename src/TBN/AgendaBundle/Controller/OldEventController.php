<?php

namespace TBN\AgendaBundle\Controller;

use TBN\MainBundle\Controller\TBNController as Controller;

class OldEventController extends Controller
{

    public function detailsAction($slug)
    {
        return $this->redirectToRoute('tbn_agenda_details_old', [
            'slug' => $slug
        ]);
    }

    public function tendancesAction($slug)
    {
        return $this->redirectToRoute('tbn_agenda_details_old', [
            'slug' => $slug
        ]);
    }

    public function fbMembresAction($slug, $page)
    {
        dump($this->generateUrl('tbn_agenda_soirees_membres_old', [
            'slug' => $slug,
            'page' => $page
        ]));

        die;

        return $this->redirectToRoute('tbn_agenda_soirees_membres_old', [
            'slug' => $slug,
            'page' => $page
        ]);
    }

    public function soireesSimilairesAction($slug, $page)
    {
        return $this->redirectToRoute('tbn_agenda_soirees_similaires_old', [
            'slug' => $slug,
            'page' => $page
        ]);
    }

    public function listAction($page)
    {
        return $this->redirectToRoute('tbn_agenda_pagination', [
            'page' => $page
        ]);
    }
}
