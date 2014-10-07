<?php

namespace TBN\AgendaBundle\Controller;

use TBN\MainBundle\Controller\TBNController as Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FeedController extends Controller
{
    /**
     * Generate the article feed
     *
     * @return Response XML Feed
     */
    public function feedAction($format)
    {
        //$format = $request->getRequestFormat();
        $agendas = $this->getDoctrine()->getRepository('TBNAgendaBundle:Agenda')->findAll();

        $feed = $this->get('eko_feed.feed.manager')->get('agenda');
        $feed->addFromArray($agendas);

        return new Response($feed->render($format), 200, ['Content-Type' => 'xml']); // or 'atom'
    }
}