<?php

namespace AppBundle\Controller\City;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use AppBundle\Repository\AgendaRepository;
use AppBundle\Entity\Comment;
use AppBundle\Form\Type\CommentType;
use AppBundle\Controller\TBNController as Controller;
use SocialLinks\Page;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use FOS\HttpCacheBundle\Configuration\Tag;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use AppBundle\Configuration\BrowserCache;

use AppBundle\Entity\Site;
use AppBundle\Entity\Agenda;
use AppBundle\Entity\Place;
use AppBundle\Entity\Calendrier;

use AppBundle\Form\Type\SearchType;
use AppBundle\Search\SearchAgenda;
use Symfony\Component\HttpFoundation\RedirectResponse;
use AppBundle\Invalidator\EventInvalidator;
use AppBundle\Entity\User;

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
     * @Route("/soiree/{slug}--{id}.html", name="tbn_agenda_details", requirements={"slug": ".+", "id": "\d+"})
     * @BrowserCache(false)
     */
    public function detailsAction($slug, $id = null)
    {
        $result = $this->checkEventUrl($slug, $id);
        if ($result instanceof Response) {
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

        $response = $this->render('Agenda/details.html.twig', [
            'soiree' => $agenda,
            'form' => $form->createView(),
            'nb_comments' => $nbComments,
            'stats' => $this->getAgendaStats($agenda)
        ]);

        $now = new \DateTime();
        if ($agenda->getDateFin() < $now) {
            $expires = $now;
            $expires->modify("+1 year");
            $ttl = 31536000;
        } else {
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
    public function shareAction(Agenda $agenda)
    {
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
        $repo = $em->getRepository("AppBundle:Agenda");

        $participer = false;
        $interet = false;

        $user = $this->getUser();
        if ($user) {
            /**
             * @var User $user
             */
            $repoCalendrier = $em->getRepository('AppBundle:Calendrier');
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
                    $stats = $api->getUserEventStats($agenda->getFacebookEventId(), $user->getInfo()->getFacebookId(), $user->getInfo()->getFacebookAccessToken());
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
}
